<?php

namespace Catalog\Contracts;

use Spatie\LaravelData\Data;

/**
 * DTO hạng vé trả ra cho module khác (QĐ-3.4). `price` là số nguyên yên
 * (YC-2.2); `remaining` là số vé còn bán được tại thời điểm đọc (YC-6.4).
 */
class TicketTypeInfo extends Data
{
    public function __construct(
        public int $id,
        public int $eventId,
        public string $name,
        public int $price,
        public int $remaining,
    ) {}
}
