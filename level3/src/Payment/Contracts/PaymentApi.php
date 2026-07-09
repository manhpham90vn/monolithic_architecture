<?php

namespace Payment\Contracts;

/**
 * Cửa ngõ DUY NHẤT của module Payment cho các module khác (QĐ-3.3).
 * Payment sở hữu mọi thứ liên quan Stripe: tạo phiên checkout, nhận webhook
 * và xác nhận đã trả tiền (YC-3.1). Kết quả xác nhận công bố qua event
 * PaymentSucceeded.
 */
interface PaymentApi
{
    /**
     * Tạo phiên Stripe Checkout với số tiền bằng đúng tổng đơn (YC-7.3,
     * YC-2.2). Trả về URL thanh toán, hoặc null khi chưa cấu hình
     * STRIPE_SECRET (môi trường test/dev không có khoá) — khi đó bỏ qua
     * bước Stripe.
     */
    public function createCheckoutSession(CheckoutSessionData $data): ?string;
}
