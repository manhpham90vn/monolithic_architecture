<?php

namespace App\Actions\Payment;

use App\Data\PaymentConfirmationData;
use App\Events\OrderPaid;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Đánh dấu đơn đã thanh toán khi có xác nhận server-side từ Stripe (YC-9.2).
 * Idempotent: gọi lặp lại KHÔNG phát hành vé hay gửi mail quá một lần
 * (YC-9.3). Việc phụ (phát hành vé, gửi mail) đẩy qua event OrderPaid —
 * Action chỉ lo nghiệp vụ chính (QĐ-2.5).
 */
class ConfirmPayment
{
    public function handle(PaymentConfirmationData $data): void
    {
        DB::transaction(function () use ($data): void {
            $order = Order::query()
                ->when($data->stripeSessionId, fn ($query) => $query->where('stripe_session_id', $data->stripeSessionId))
                ->when(! $data->stripeSessionId && $data->orderId, fn ($query) => $query->whereKey($data->orderId))
                ->lockForUpdate()
                ->first();

            // Đơn không tìm thấy → bỏ qua (webhook lạ).
            if ($order === null) {
                return;
            }

            // Đã thanh toán rồi → no-op, đảm bảo idempotent (YC-9.3).
            if (! $order->isPending()) {
                return;
            }

            // Đơn đã quá hạn giữ vé → cho hết hạn, không phát hành vé nữa
            // để tránh bán quá số (YC-9.1). Thực tế sẽ cần hoàn tiền.
            if ($order->expires_at !== null && $order->expires_at->isPast()) {
                $order->update(['status' => Order::STATUS_EXPIRED]);

                return;
            }

            $order->update([
                'status' => Order::STATUS_PAID,
                'paid_at' => now(),
                'expires_at' => null,
                'stripe_payment_intent' => $data->paymentIntent,
            ]);

            // Phát trong transaction: listener sync (IssueTickets) chạy cùng
            // transaction nên phát hành vé là nguyên tử với đổi trạng thái;
            // listener gửi mail chờ sau commit (xem SendOrderConfirmation).
            OrderPaid::dispatch($order);
        });
    }
}
