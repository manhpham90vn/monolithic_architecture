<?php

namespace Payment\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Payment\Actions\ConfirmStripePayment;
use Payment\Data\PaymentConfirmationData;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Nhận xác nhận thanh toán từ Stripe về phía máy chủ (YC-9.2). Controller
 * chỉ kiểm chữ ký (việc của tầng HTTP) rồi giao cho Action (QĐ-2.4);
 * idempotency nằm trong Action và trong Ticketing (YC-9.3).
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request, ConfirmStripePayment $confirmStripePayment): Response
    {
        $event = $this->resolveEvent($request);

        if ($event === null) {
            return response('Invalid signature', 400);
        }

        if (($event['type'] ?? null) === 'checkout.session.completed') {
            $confirmStripePayment->handle(
                PaymentConfirmationData::fromStripeSession($event['data']['object'] ?? []),
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
}
