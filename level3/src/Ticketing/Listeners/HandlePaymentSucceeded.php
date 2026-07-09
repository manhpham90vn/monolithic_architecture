<?php

namespace Ticketing\Listeners;

use Payment\Contracts\PaymentSucceeded;
use Ticketing\Actions\ConfirmOrderPaid;

/**
 * Phản ứng với event chéo module PaymentSucceeded của Payment (QĐ-3.5):
 * thanh toán đã được Stripe xác nhận server-side → đánh dấu đơn đã thanh
 * toán và phát hành vé (YC-7.4, YC-9.2). Event xử lý sau khi transaction
 * của Payment đã commit (QĐ-3.12); idempotency nằm trong ConfirmOrderPaid
 * (YC-9.3).
 */
class HandlePaymentSucceeded
{
    public function __construct(private readonly ConfirmOrderPaid $confirmOrderPaid) {}

    public function handle(PaymentSucceeded $event): void
    {
        $this->confirmOrderPaid->handle($event->orderId);
    }
}
