<?php

namespace Ticketing\Domain\Exception;

use DomainException;

/**
 * Bất biến YC-11.3: một vé không được soát vào cửa quá một lần.
 */
class TicketAlreadyUsed extends DomainException
{
    public static function withToken(string $token): self
    {
        return new self("Vé {$token} đã được soát trước đó.");
    }
}
