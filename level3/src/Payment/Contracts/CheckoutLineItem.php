<?php

namespace Payment\Contracts;

use Spatie\LaravelData\Data;

/**
 * Một dòng trên phiên Stripe Checkout. `unitPrice` là số nguyên yên
 * (YC-2.2).
 */
class CheckoutLineItem extends Data
{
    public function __construct(
        public string $name,
        public int $unitPrice,
        public int $quantity,
    ) {}
}
