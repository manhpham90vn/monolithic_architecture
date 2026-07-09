<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Response;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TicketController extends Controller
{
    /**
     * "Vé của tôi" — toàn bộ vé đã mua (YC-10.2).
     */
    public function index(): View
    {
        $tickets = Ticket::query()
            ->where('user_id', auth()->id())
            ->with(['event', 'ticketType'])
            ->latest()
            ->get()
            ->groupBy('event_id');

        return view('tickets.index', ['ticketsByEvent' => $tickets]);
    }

    /**
     * Ảnh QR (SVG) của một vé, mã hoá token của vé (YC-10.1).
     */
    public function qr(Ticket $ticket): Response
    {
        $this->authorize('view', $ticket);

        $svg = QrCode::format('svg')->size(220)->margin(1)->generate($ticket->token);

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }
}
