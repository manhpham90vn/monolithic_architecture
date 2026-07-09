<?php

namespace Ticketing\Data;

use Spatie\LaravelData\Data;
use Ticketing\Contracts\CheckInStatus;
use Ticketing\Models\Ticket;

/**
 * Kết quả nội bộ module của Action CheckInTicket (QĐ-2.3) — còn kèm Model
 * nên KHÔNG ra khỏi module; TicketingApiImpl chuyển thành DTO CheckInResult
 * trước khi trả cho module khác (QĐ-3.4).
 */
class CheckInOutcome extends Data
{
    public function __construct(
        public CheckInStatus $status,
        public ?Ticket $ticket,
    ) {}
}
