<?php

namespace Payment\Actions;

use Illuminate\Support\Facades\DB;
use Payment\Contracts\PaymentSucceeded;
use Payment\Data\PaymentConfirmationData;
use Payment\Models\Payment;

/**
 * Ghi nhận xác nhận thanh toán server-side từ Stripe (YC-9.2) rồi công bố
 * PaymentSucceeded cho module khác phản ứng (QĐ-3.5). Idempotent ở tầng
 * Payment: bản ghi thanh toán đã succeeded thì bỏ qua webhook lặp lại;
 * Ticketing còn tự bảo vệ thêm bằng trạng thái đơn (YC-9.3).
 */
class ConfirmStripePayment
{
    public function handle(PaymentConfirmationData $data): void
    {
        DB::transaction(function () use ($data): void {
            $payment = Payment::query()
                ->when($data->stripeSessionId, fn ($query) => $query->where('stripe_session_id', $data->stripeSessionId))
                ->when(! $data->stripeSessionId, fn ($query) => $query->whereRaw('1 = 0'))
                ->lockForUpdate()
                ->first();

            if ($payment !== null) {
                // Đã xử lý rồi → no-op (YC-9.3).
                if ($payment->isSucceeded()) {
                    return;
                }

                $payment->update([
                    'status' => Payment::STATUS_SUCCEEDED,
                    'stripe_payment_intent' => $data->paymentIntent,
                ]);

                $orderId = $payment->order_id;
            } else {
                // Không có bản ghi (môi trường dev/test không tạo phiên
                // Stripe) → tin metadata order_id trong payload.
                $orderId = $data->orderId === null ? null : (int) $data->orderId;
            }

            if ($orderId === null) {
                return;
            }

            // Event ShouldDispatchAfterCommit: module khác chỉ thấy sau khi
            // transaction này commit (QĐ-3.12).
            PaymentSucceeded::dispatch($orderId, $data->paymentIntent);
        });
    }
}
