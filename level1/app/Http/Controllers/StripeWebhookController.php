<?php

namespace App\Http\Controllers;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    /**
     * Nhận xác nhận thanh toán từ Stripe về phía máy chủ (YC-9.2). Xử lý
     * idempotent: gọi lặp lại KHÔNG phát hành vé hay trừ kho quá một lần
     * (YC-9.3). Nghiệp vụ nằm ngay trong controller (bản mức 1).
     */
    public function handle(Request $request): Response
    {
        $event = $this->resolveEvent($request);

        if ($event === null) {
            return response('Invalid signature', 400);
        }

        if (($event['type'] ?? null) === 'checkout.session.completed') {
            $session = $event['data']['object'] ?? [];
            $this->markOrderPaid(
                sessionId: $session['id'] ?? null,
                orderId: $session['metadata']['order_id'] ?? null,
                paymentIntent: $session['payment_intent'] ?? null,
            );
        }

        return response('OK', 200);
    }

    /**
     * Kiểm chữ ký webhook khi có STRIPE_WEBHOOK_SECRET; nếu không cấu hình
     * (test/dev) thì đọc thẳng JSON.
     *
     * @return array<string, mixed>|null null nghĩa là chữ ký không hợp lệ.
     */
    protected function resolveEvent(Request $request): ?array
    {
        $secret = config('services.stripe.webhook_secret');

        if (blank($secret)) {
            return $request->json()->all();
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                $secret,
            );

            return $event->toArray();
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    protected function markOrderPaid(?string $sessionId, ?string $orderId, ?string $paymentIntent): void
    {
        $shouldSendEmail = DB::transaction(function () use ($sessionId, $orderId, $paymentIntent): bool {
            $order = Order::query()
                ->when($sessionId, fn ($query) => $query->where('stripe_session_id', $sessionId))
                ->when(! $sessionId && $orderId, fn ($query) => $query->whereKey($orderId))
                ->lockForUpdate()
                ->first();

            // Đơn không tìm thấy → bỏ qua (webhook lạ).
            if ($order === null) {
                return false;
            }

            // Đã thanh toán rồi → no-op, đảm bảo idempotent (YC-9.3).
            if (! $order->isPending()) {
                return false;
            }

            // Đơn đã quá hạn giữ vé → để scheduler cho hết hạn, không phát hành
            // vé nữa để tránh bán quá số (YC-9.1). Thực tế sẽ cần hoàn tiền.
            if ($order->expires_at !== null && $order->expires_at->isPast()) {
                $order->update(['status' => Order::STATUS_EXPIRED]);

                return false;
            }

            $order->update([
                'status' => Order::STATUS_PAID,
                'paid_at' => now(),
                'expires_at' => null,
                'stripe_payment_intent' => $paymentIntent,
            ]);

            // Phát hành mỗi vé một mã QR riêng (YC-10.1).
            foreach ($order->items as $item) {
                for ($i = 0; $i < $item->quantity; $i++) {
                    Ticket::create([
                        'order_id' => $order->id,
                        'ticket_type_id' => $item->ticket_type_id,
                        'event_id' => $order->event_id,
                        'user_id' => $order->user_id,
                        'token' => (string) Str::ulid(),
                        'status' => Ticket::STATUS_ISSUED,
                    ]);
                }
            }

            return true;
        });

        if ($shouldSendEmail) {
            $order = Order::with(['user', 'event', 'tickets.ticketType'])->find(
                $sessionId
                    ? Order::where('stripe_session_id', $sessionId)->value('id')
                    : $orderId
            );

            // Email xác nhận kèm vé và mã QR (YC-7.4, YC-12.1).
            Mail::to($order->user->email)->send(new OrderConfirmationMail($order));
        }
    }
}
