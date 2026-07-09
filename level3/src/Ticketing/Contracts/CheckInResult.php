<?php

namespace Ticketing\Contracts;

use Spatie\LaravelData\Data;

/**
 * Kết quả TicketingApi::checkIn() trả cho module CheckIn (QĐ-2.3, QĐ-3.4).
 */
class CheckInResult extends Data
{
    public function __construct(
        public CheckInStatus $status,
        public ?TicketSummary $ticket,
        public string $scannedToken,
    ) {}
}
