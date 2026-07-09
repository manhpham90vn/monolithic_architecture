<?php

namespace Ticketing\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dòng đơn — chốt cả giá lẫn tên hạng vé tại thời điểm tạo đơn (YC-8.5):
 * `ticket_type_id` chỉ là ID tham chiếu sang Catalog (QĐ-3.7), nên tên
 * hạng vé được chụp lại để hiển thị mà không phải gọi Catalog.
 */
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['order_id', 'ticket_type_id', 'ticket_type_name', 'quantity', 'unit_price'];

    protected function casts(): array
    {
        return [
            'ticket_type_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subtotal(): int
    {
        return $this->quantity * $this->unit_price;
    }

    protected static function newFactory(): Factory
    {
        return OrderItemFactory::new();
    }
}
