<?php

namespace Ticketing\Contracts;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * Thông tin vé trả ra cho module khác khi soát vé — DTO, không phải
 * Eloquent Model (QĐ-3.4).
 */
class TicketSummary extends Data
{
    public function __construct(
        public string $eventTitle,
        public string $ticketTypeName,
        public string $buyerName,
        public ?CarbonImmutable $usedAt,
    ) {}
}
