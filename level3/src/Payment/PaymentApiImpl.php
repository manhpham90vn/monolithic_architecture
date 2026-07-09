<?php

namespace Payment;

use Payment\Actions\CreateCheckoutSession;
use Payment\Contracts\CheckoutSessionData;
use Payment\Contracts\PaymentApi;

/**
 * Implementation của Payment\Contracts\PaymentApi — internal, bind trong
 * PaymentServiceProvider (QĐ-3.2, QĐ-3.3).
 */
class PaymentApiImpl implements PaymentApi
{
    public function __construct(private readonly CreateCheckoutSession $createCheckoutSession) {}

    public function createCheckoutSession(CheckoutSessionData $data): ?string
    {
        return $this->createCheckoutSession->handle($data);
    }
}
