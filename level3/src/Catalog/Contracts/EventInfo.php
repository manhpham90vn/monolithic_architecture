<?php

namespace Catalog\Contracts;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * DTO sự kiện trả ra cho module khác — Public API trả DTO, không trả
 * Eloquent Model (QĐ-3.4).
 */
class EventInfo extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public string $venue,
        public CarbonImmutable $startsAt,
        public bool $isPublished,
    ) {}
}
