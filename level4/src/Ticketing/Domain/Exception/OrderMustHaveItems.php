<?php

namespace Ticketing\Domain\Exception;

use DomainException;

/**
 * Bất biến §7.1: một đơn phải có ít nhất một vé.
 */
final class OrderMustHaveItems extends DomainException
{
    public function __construct()
    {
        parent::__construct('Đơn phải có ít nhất một vé.');
    }
}
