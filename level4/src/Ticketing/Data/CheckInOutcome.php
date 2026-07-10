<?php

namespace Ticketing\Data;

use Spatie\LaravelData\Data;
use Ticketing\Contracts\CheckInStatus;
use Ticketing\Domain\Ticket\Ticket;

/**
 * Kết quả nội bộ module của use-case CheckInTicket (QĐ-2.3). Kèm aggregate
 * domain Ticket nên KHÔNG ra khỏi module; TicketingApiImpl chuyển thành DTO
 * CheckInResult trước khi trả cho module khác (QĐ-3.4).
 */
class CheckInOutcome extends Data
{
    public function __construct(
        public CheckInStatus $status,
        public ?Ticket $ticket,
    ) {}
}
