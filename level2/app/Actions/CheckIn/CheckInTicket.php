<?php

namespace App\Actions\CheckIn;

use App\Data\CheckInResult;
use App\Enums\CheckInStatus;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * Soát một mã QR: trả kết quả hợp lệ / đã dùng / không tồn tại (YC-11.1).
 * Vé hợp lệ được chuyển sang "đã dùng" và không soát được lần hai — khoá
 * hàng để chặn hai lần quét đồng thời (YC-11.2, YC-11.3).
 */
class CheckInTicket
{
    public function handle(string $token): CheckInResult
    {
        return DB::transaction(function () use ($token): CheckInResult {
            $ticket = Ticket::query()
                ->where('token', $token)
                ->lockForUpdate()
                ->first();

            if ($ticket === null) {
                return new CheckInResult(CheckInStatus::Nonexistent, null, $token);
            }

            if ($ticket->isUsed()) {
                return new CheckInResult(CheckInStatus::Used, $ticket, $token);
            }

            $ticket->update([
                'status' => Ticket::STATUS_USED,
                'used_at' => now(),
            ]);

            return new CheckInResult(CheckInStatus::Valid, $ticket, $token);
        });
    }
}
