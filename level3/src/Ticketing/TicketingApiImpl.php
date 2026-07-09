<?php

namespace Ticketing;

use App\Models\User;
use Catalog\Contracts\CatalogApi;
use Ticketing\Actions\CheckInTicket;
use Ticketing\Contracts\CheckInResult;
use Ticketing\Contracts\TicketingApi;
use Ticketing\Contracts\TicketSummary;
use Ticketing\Models\Ticket;

/**
 * Implementation của Ticketing\Contracts\TicketingApi — internal, bind
 * trong TicketingServiceProvider (QĐ-3.2, QĐ-3.3). Chuyển Model nội bộ
 * thành DTO trước khi trả ra ngoài (QĐ-3.4).
 */
class TicketingApiImpl implements TicketingApi
{
    public function __construct(
        private readonly CheckInTicket $checkInTicket,
        private readonly CatalogApi $catalog,
    ) {}

    public function checkIn(string $token): CheckInResult
    {
        $outcome = $this->checkInTicket->handle($token);

        return new CheckInResult(
            status: $outcome->status,
            ticket: $outcome->ticket === null ? null : $this->toSummary($outcome->ticket),
            scannedToken: $token,
        );
    }

    private function toSummary(Ticket $ticket): TicketSummary
    {
        // Thông tin sự kiện lấy qua Public API của Catalog (QĐ-3.3), người
        // mua lấy từ hạ tầng users (QĐ-3.9).
        $eventInfo = $this->catalog->eventInfo($ticket->event_id);

        return new TicketSummary(
            eventTitle: $eventInfo?->title ?? '—',
            ticketTypeName: $ticket->ticket_type_name,
            buyerName: User::find($ticket->user_id)?->name ?? '—',
            usedAt: $ticket->used_at?->toImmutable(),
        );
    }
}
