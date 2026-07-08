<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CheckInController extends Controller
{
    /**
     * Màn hình soát vé cho nhân viên (§11). Quyền truy cập do gate 'check-in'
     * kiểm soát ở route (QĐ-1.1).
     */
    public function create(): View
    {
        return view('checkin.index');
    }

    /**
     * Soát một mã QR: trả kết quả hợp lệ / đã dùng / không tồn tại (YC-11.1).
     * Vé hợp lệ được chuyển sang "đã dùng" và không soát được lần hai
     * (YC-11.2, YC-11.3).
     */
    public function store(Request $request): View
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        [$result, $ticket] = DB::transaction(function () use ($validated): array {
            $ticket = Ticket::query()
                ->where('token', $validated['token'])
                ->lockForUpdate()
                ->first();

            if ($ticket === null) {
                return ['nonexistent', null];
            }

            if ($ticket->isUsed()) {
                return ['used', $ticket];
            }

            $ticket->update([
                'status' => Ticket::STATUS_USED,
                'used_at' => now(),
            ]);

            return ['valid', $ticket];
        });

        $ticket?->loadMissing(['event', 'ticketType', 'user']);

        return view('checkin.index', [
            'result' => $result,
            'ticket' => $ticket,
            'scannedToken' => $validated['token'],
        ]);
    }
}
