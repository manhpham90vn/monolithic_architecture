<?php

namespace Ticketing\Models;

use App\Models\User;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vé điện tử (§10) — internal của Ticketing. `event_id`/`ticket_type_id`
 * là ID tham chiếu sang Catalog, không có relationship chéo module
 * (QĐ-3.7); tên hạng vé chụp tại thời điểm phát hành.
 */
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

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

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
