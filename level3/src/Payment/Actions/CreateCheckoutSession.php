<?php

namespace Payment\Actions;

use Payment\Contracts\CheckoutLineItem;
use Payment\Contracts\CheckoutSessionData;
use Payment\Models\Payment;
use Stripe\Checkout\Session;
use Stripe\Stripe;

/**
 * Tạo phiên Stripe Checkout với số tiền bằng đúng tổng đơn (YC-7.3,
 * YC-2.2) và ghi lại bản ghi thanh toán để webhook đối chiếu.
 */
class CreateCheckoutSession
{
    public function handle(CheckoutSessionData $data): ?string
    {
        $secret = config('services.stripe.secret');

        if (blank($secret)) {
            return null;
        }

        Stripe::setApiKey($secret);

        $session = Session::create([
            'mode' => 'payment',
            'customer_email' => $data->customerEmail,
            'line_items' => array_map(fn (CheckoutLineItem $item): array => [
                'quantity' => $item->quantity,
                'price_data' => [
                    'currency' => 'jpy', // Tiền tệ JPY (YC-2.2).
                    'unit_amount' => $item->unitPrice,
                    'product_data' => ['name' => $item->name],
                ],
            ], $data->lineItems),
            'success_url' => $data->successUrl,
            'cancel_url' => $data->cancelUrl,
            'metadata' => ['order_id' => (string) $data->orderId],
        ]);

        Payment::create([
            'order_id' => $data->orderId,
            'amount' => $data->amount,
            'status' => Payment::STATUS_PENDING,
            'stripe_session_id' => $session->id,
        ]);

        return $session->url;
    }
}
