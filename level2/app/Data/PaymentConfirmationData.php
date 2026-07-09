<?php

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * Xác nhận thanh toán từ Stripe (đã bóc từ payload webhook) truyền vào
 * Action ConfirmPayment (QĐ-2.3).
 */
class PaymentConfirmationData extends Data
{
    public function __construct(
        public ?string $stripeSessionId,
        public ?string $orderId,
        public ?string $paymentIntent,
    ) {}

    /**
     * @param  array<string, mixed>  $session  object `checkout.session` trong payload webhook
     */
    public static function fromStripeSession(array $session): self
    {
        return new self(
            stripeSessionId: $session['id'] ?? null,
            orderId: $session['metadata']['order_id'] ?? null,
            paymentIntent: $session['payment_intent'] ?? null,
        );
    }
}
