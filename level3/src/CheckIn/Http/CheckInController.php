<?php

namespace CheckIn\Http;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Ticketing\Contracts\TicketingApi;

/**
 * Màn hình soát vé cho nhân viên (§11). Quyền truy cập do gate 'check-in'
 * kiểm soát ở route (YC-4.2). Trạng thái vé thuộc sở hữu của Ticketing nên
 * việc soát đi qua Public API của Ticketing — cần kết quả trả về nên là
 * lời gọi API, không phải event (QĐ-3.3, QĐ-3.5).
 */
class CheckInController extends Controller
{
    public function create(): View
    {
        return view('checkin::index');
    }

    public function store(CheckInRequest $request, TicketingApi $ticketing): View
    {
        $result = $ticketing->checkIn($request->token());

        return view('checkin::index', [
            'result' => $result->status,
            'ticket' => $result->ticket,
            'scannedToken' => $result->scannedToken,
        ]);
    }
}
