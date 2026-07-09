<?php

namespace Payment\Contracts;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event chéo module: Stripe đã xác nhận thanh toán server-side cho một đơn
 * (YC-9.2). Một chiều — Payment không cần kết quả từ module phản ứng
 * (QĐ-3.5); payload chỉ scalar (QĐ-3.4); xử lý sau khi transaction của
 * Payment commit (QĐ-3.12).
 */
class PaymentSucceeded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $orderId,
        public ?string $paymentIntent,
    ) {}
}
