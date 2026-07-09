<?php

namespace Ticketing\Models;

use App\Models\User;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model internal của Ticketing (QĐ-3.4). `event_id` chỉ là ID tham chiếu
 * sang Catalog — không có relationship/FK chéo module (QĐ-3.7); cần thông
 * tin sự kiện thì gọi CatalogApi. Quan hệ về `users` được phép vì User là
 * hạ tầng ở app/, không phải module (QĐ-3.9).
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'event_id', 'status', 'total_amount', 'expires_at', 'paid_at',
    ];

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_PAID = 'paid';

    public const string STATUS_EXPIRED = 'expired';

    public const string STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
            'event_id' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * @return array<int, int> [ticket_type_id => số lượng] của đơn
     */
    public function itemQuantities(): array
    {
        return $this->items
            ->mapWithKeys(fn (OrderItem $item): array => [$item->ticket_type_id => $item->quantity])
            ->all();
    }

    protected static function newFactory(): Factory
    {
        return OrderFactory::new();
    }
}
