<?php

namespace Ticketing\Infrastructure\Persistence;

use App\Models\User;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model persistence cho bảng `tickets` (QĐ-4.3) — vé điện tử (§10). Việc
 * đổi trạng thái soát vé đi qua aggregate Ticketing\Domain\Ticket\Ticket +
 * TicketRepository; model này lo đọc/ghi DB, route-binding và phần đọc
 * ("Vé của tôi"). `event_id`/`ticket_type_id` là ID tham chiếu Catalog
 * (QĐ-3.7); quan hệ `users` được phép (QĐ-3.9).
 */
class TicketEloquentModel extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    protected $table = 'tickets';

    /** @var list<string> */
    protected $fillable = [
        'order_id', 'ticket_type_id', 'ticket_type_name', 'event_id', 'user_id',
        'token', 'status', 'used_at',
    ];

    public const string STATUS_ISSUED = 'issued';

    public const string STATUS_USED = 'used';

    protected function casts(): array
    {
        return [
            'ticket_type_id' => 'integer',
            'event_id' => 'integer',
            'used_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OrderEloquentModel, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderEloquentModel::class, 'order_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isUsed(): bool
    {
        return $this->status === self::STATUS_USED;
    }

    protected static function newFactory(): Factory
    {
        return TicketFactory::new();
    }
}
