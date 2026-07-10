<?php

namespace Ticketing\Domain\Order;

/**
 * Sinh token duy nhất cho vé điện tử. Interface nằm trong Domain, hiện
 * thực (ULID) nằm ở Infrastructure — Domain không biết cơ chế sinh token
 * của framework (QĐ-4.1, QĐ-4.2).
 */
interface TokenGenerator
{
    public function generate(): string;
}
