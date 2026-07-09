<?php

namespace App\Actions\Payment;

use App\Models\Order;
use Stripe\Checkout\Session;
use Stripe\Stripe;

/**
 * Tạo phiên Stripe Checkout với số tiền bằng đúng tổng đơn (YC-7.3, YC-2.2).
 * Trả về URL thanh toán, hoặc null khi chưa cấu hình STRIPE_SECRET
 * (môi trường test/dev không có khoá) — khi đó bỏ qua bước Stripe.
 */
class CreateStripeCheckout
{
    public function handle(Order $order): ?string
    {
        $secret = config('services.stripe.secret');

        if (blank($secret)) {
            return null;
        }

        $order->loadMissing(['user', 'event', 'items.ticketType']);

        Stripe::setApiKey($secret);

        $session = Session::create([
            'mode' => 'payment',
            'customer_email' => $order->user->email,
            'line_items' => $order->items->map(fn ($item): array => [
                'quantity' => $item->quantity,
                'price_data' => [
                    'currency' => 'jpy', // Tiền tệ JPY (YC-2.2).
                    'unit_amount' => $item->unit_price,
                    'product_data' => [
                        'name' => $order->event->title.' — '.$item->ticketType->name,
                    ],
                ],
            ])->all(),
            'success_url' => route('orders.show', $order).'?checkout=success',
            'cancel_url' => route('orders.show', $order).'?checkout=cancel',
            'metadata' => ['order_id' => (string) $order->id],
        ]);

        $order->update(['stripe_session_id' => $session->id]);

        return $session->url;
    }
}
