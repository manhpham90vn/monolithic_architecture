<?php

namespace Ticketing\Infrastructure\Persistence;

use App\Models\User;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model persistence — CHỈ để map bảng `orders` (QĐ-4.3). Bất biến nghiệp vụ
 * KHÔNG nằm ở đây mà ở aggregate domain Ticketing\Domain\Order\Order; model
 * này chỉ lo đọc/ghi DB, route-binding và phần đọc (hiển thị đơn). Việc
 * ghi trạng thái luôn đi qua aggregate + Repository, không qua `->update()`.
 *
 * `event_id` là ID tham chiếu sang Catalog — không FK/relationship chéo
 * module (QĐ-3.7). Quan hệ về `users` được phép (QĐ-3.9).
 */
class OrderEloquentModel extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $table = 'orders';

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
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<OrderItemEloquentModel, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItemEloquentModel::class, 'order_id');
    }

    /** @return HasMany<TicketEloquentModel, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(TicketEloquentModel::class, 'order_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    protected static function newFactory(): Factory
    {
        return OrderFactory::new();
    }
}
