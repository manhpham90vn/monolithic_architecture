<?php

namespace Ticketing\Infrastructure\Persistence;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model persistence cho bảng `order_items` (QĐ-4.3). Chốt cả giá lẫn tên
 * hạng vé tại thời điểm tạo đơn (YC-8.5); `ticket_type_id` là ID tham chiếu
 * sang Catalog (QĐ-3.7).
 */
class OrderItemEloquentModel extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $table = 'order_items';

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

    /** @return BelongsTo<OrderEloquentModel, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderEloquentModel::class, 'order_id');
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
