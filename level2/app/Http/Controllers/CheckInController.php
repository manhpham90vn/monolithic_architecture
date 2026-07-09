<?php

namespace App\Http\Controllers;

use App\Actions\CheckIn\CheckInTicket;
use App\Http\Requests\CheckInRequest;
use Illuminate\View\View;

/**
 * Màn hình soát vé cho nhân viên (§11). Quyền truy cập do gate 'check-in'
 * kiểm soát ở route (YC-4.2); nghiệp vụ soát nằm trong Action CheckInTicket
 * (QĐ-2.4).
 */
class CheckInController extends Controller
{
    public function create(): View
    {
        return view('checkin.index');
    }

    public function store(CheckInRequest $request, CheckInTicket $checkInTicket): View
    {
        $result = $checkInTicket->handle($request->token());

        $result->ticket?->loadMissing(['event', 'ticketType', 'user']);

        return view('checkin.index', [
            'result' => $result->status,
            'ticket' => $result->ticket,
            'scannedToken' => $result->scannedToken,
        ]);
    }
}
