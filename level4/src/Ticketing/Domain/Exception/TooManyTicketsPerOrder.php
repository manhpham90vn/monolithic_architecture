<?php

namespace Ticketing\Domain\Exception;

use DomainException;

/**
 * Bất biến YC-8.1: mỗi đơn không được quá 10 vé (tổng qua các hạng). Được
 * bảo vệ NGAY TRONG aggregate — không chỗ nào tạo được đơn vi phạm.
 */
final class TooManyTicketsPerOrder extends DomainException
{
    public static function max(int $requested, int $max): self
    {
        return new self("Mỗi đơn không được quá {$max} vé (yêu cầu {$requested}).");
    }
}
