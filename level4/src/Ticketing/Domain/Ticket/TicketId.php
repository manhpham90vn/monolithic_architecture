<?php

namespace Ticketing\Domain\Ticket;

use InvalidArgumentException;

/**
 * Định danh vé — Value Object.
 */
final class TicketId
{
    public function __construct(public readonly int $value)
    {
        if ($value < 1) {
            throw new InvalidArgumentException('TicketId phải là số nguyên dương.');
        }
    }
}
