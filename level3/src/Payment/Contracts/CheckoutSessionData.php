<?php

namespace Payment\Contracts;

use Spatie\LaravelData\Data;

/**
 * Yêu cầu tạo phiên thanh toán mà module khác gửi vào PaymentApi (QĐ-2.3,
 * QĐ-3.3). `amount` là số nguyên yên (YC-2.2).
 */
class CheckoutSessionData extends Data
{
    /**
     * @param  list<CheckoutLineItem>  $lineItems
     */
    public function __construct(
        public int $orderId,
        public string $customerEmail,
        public int $amount,
        public array $lineItems,
        public string $successUrl,
        public string $cancelUrl,
    ) {}
}
