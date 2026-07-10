<?php

namespace Ticketing\Http;

use App\Http\Controllers\Controller;
use Catalog\Contracts\CatalogApi;
use Illuminate\Http\Response;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Ticketing\Infrastructure\Persistence\TicketEloquentModel;

class TicketController extends Controller
{
    /**
     * "Vé của tôi" — toàn bộ vé đã mua (YC-10.2). Đường đọc: query thẳng
     * model persistence. Thông tin sự kiện lấy batch qua Public API của
     * Catalog, 1 lần cho N sự kiện (QĐ-3.3).
     */
    public function index(CatalogApi $catalog): View
    {
        $ticketsByEvent = TicketEloquentModel::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->get()
            ->groupBy('event_id');

        return view('ticketing::tickets.index', [
            'ticketsByEvent' => $ticketsByEvent,
            'eventInfos' => $catalog->eventInfos($ticketsByEvent->keys()->all()),
        ]);
    }

    /**
     * Ảnh QR (SVG) của một vé, mã hoá token của vé (YC-10.1).
     */
    public function qr(TicketEloquentModel $ticket): Response
    {
        $this->authorize('view', $ticket);

        $svg = QrCode::format('svg')->size(220)->margin(1)->generate($ticket->token);

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }
}
