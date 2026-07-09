<?php

namespace App\Models;

use Database\Factories\TicketTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    /** @use HasFactory<TicketTypeFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['event_id', 'name', 'price', 'quantity'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Số vé đã bị "chiếm": đơn đã thanh toán (trừ vĩnh viễn) cộng đơn chờ
     * thanh toán chưa hết hạn (đang giữ). Đơn hết hạn/đã hủy không tính,
     * tức vé đã được trả lại (YC-8.2, YC-8.4).
     */
    public function reservedQuantity(): int
    {
        return (int) $this->orderItems()
            ->whereHas('order', function (Builder $query): void {
                $query->where('status', Order::STATUS_PAID)
                    ->orWhere(function (Builder $query): void {
                        $query->where('status', Order::STATUS_PENDING)
                            ->where('expires_at', '>', now());
                    });
            })
            ->sum('quantity');
    }

    /**
     * Số vé còn có thể bán của hạng này (YC-6.4).
     */
    public function remaining(): int
    {
        return max(0, $this->quantity - $this->reservedQuantity());
    }

    public function isSoldOut(): bool
    {
        return $this->remaining() <= 0;
    }
}
