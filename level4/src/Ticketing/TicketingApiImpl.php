<?php

namespace Ticketing;

use App\Models\User;
use Carbon\CarbonImmutable;
use Catalog\Contracts\CatalogApi;
use Ticketing\Application\CheckInTicketHandler;
use Ticketing\Contracts\CheckInResult;
use Ticketing\Contracts\TicketingApi;
use Ticketing\Contracts\TicketSummary;
use Ticketing\Domain\Ticket\Ticket;

/**
 * Implementation của Ticketing\Contracts\TicketingApi — internal, bind
 * trong TicketingServiceProvider (QĐ-3.2, QĐ-3.3). Chuyển aggregate nội bộ
 * thành DTO trước khi trả ra ngoài (QĐ-3.4).
 */
class TicketingApiImpl implements TicketingApi
{
    public function __construct(
        private readonly CheckInTicketHandler $checkInTicket,
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
        $eventInfo = $this->catalog->eventInfo($ticket->eventId());

        return new TicketSummary(
            eventTitle: $eventInfo?->title ?? '—',
            ticketTypeName: $ticket->ticketTypeName(),
            buyerName: User::find($ticket->userId())?->name ?? '—',
            usedAt: $ticket->usedAt() === null ? null : CarbonImmutable::instance($ticket->usedAt()),
        );
    }
}
