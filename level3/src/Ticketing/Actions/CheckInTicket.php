<?php

namespace Ticketing\Actions;

use Illuminate\Support\Facades\DB;
use Ticketing\Contracts\CheckInStatus;
use Ticketing\Data\CheckInOutcome;
use Ticketing\Models\Ticket;

/**
 * Soát một mã QR: trả kết quả hợp lệ / đã dùng / không tồn tại (YC-11.1).
 * Vé hợp lệ được chuyển sang "đã dùng" và không soát được lần hai — khoá
 * hàng để chặn hai lần quét đồng thời (YC-11.2, YC-11.3).
 */
class CheckInTicket
{
    public function handle(string $token): CheckInOutcome
    {
        return DB::transaction(function () use ($token): CheckInOutcome {
            $ticket = Ticket::query()
                ->where('token', $token)
                ->lockForUpdate()
                ->first();

            if ($ticket === null) {
                return new CheckInOutcome(CheckInStatus::Nonexistent, null);
            }

            if ($ticket->isUsed()) {
                return new CheckInOutcome(CheckInStatus::Used, $ticket);
            }

            $ticket->update([
                'status' => Ticket::STATUS_USED,
                'used_at' => now(),
            ]);

            return new CheckInOutcome(CheckInStatus::Valid, $ticket);
        });
    }
}
