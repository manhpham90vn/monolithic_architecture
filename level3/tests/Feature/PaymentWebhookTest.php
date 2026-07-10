<?php

use App\Models\User;
use Catalog\Models\Event;
use Catalog\Models\TicketType;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Mail;
use Payment\Contracts\PaymentSucceeded;
use Payment\Models\Payment;
use Ticketing\Actions\PlaceOrder;
use Ticketing\Data\PlaceOrderData;
use Ticketing\Mail\OrderConfirmationMail;
use Ticketing\Models\Order;

/*
| Webhook Stripe xác nhận thanh toán server-side (YC-9.2) → phát hành vé
| (YC-10.1) → chốt kho (YC-8.4), idempotent (YC-9.3). Ở mức 3, luồng đi qua
| hai module: Payment nhận webhook và công bố PaymentSucceeded (QĐ-3.5),
| Ticketing lắng nghe để đánh dấu đơn đã trả và phát hành vé.
*/

/**
 * Đặt một đơn thật (Catalog giữ vé) kèm bản ghi Payment đang chờ.
 *
 * @return array{Order, TicketType, Payment}
 */
function pendingOrderWithPayment(int $quantity = 2): array
{
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create(['quantity' => 10, 'price' => 3000]);
    $user = User::factory()->create();

    $order = app(PlaceOrder::class)->handle(new PlaceOrderData($user->id, $event->id, [$ticketType->id => $quantity]));

    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'amount' => $order->total_amount,
        'status' => Payment::STATUS_PENDING,
    ]);

    return [$order, $ticketType, $payment];
}

/**
 * @return array<string, mixed>
 */
function completedSessionPayload(Order $order, Payment $payment): array
{
    return [
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => $payment->stripe_session_id,
            'payment_intent' => 'pi_test_123',
            'metadata' => ['order_id' => (string) $order->id],
        ]],
    ];
}

it('marks the order paid and issues one ticket per quantity via the webhook (YC-9.2, YC-10.1)', function () {
    Mail::fake();
    [$order, $ticketType, $payment] = pendingOrderWithPayment(quantity: 2);

    $this->postJson(route('stripe.webhook'), completedSessionPayload($order, $payment))->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_PAID)
        ->and($order->fresh()->paid_at)->not->toBeNull()
        ->and($order->fresh()->tickets()->count())->toBe(2)
        ->and($payment->fresh()->status)->toBe(Payment::STATUS_SUCCEEDED)
        // Vé đã bán vĩnh viễn: còn 8, không còn giữ (YC-8.4).
        ->and($ticketType->fresh()->remaining())->toBe(8)
        ->and($ticketType->fresh()->reserved_count)->toBe(0);

    Mail::assertSent(OrderConfirmationMail::class, 1);
});

it('is idempotent: repeated webhooks do not issue extra tickets or emails (YC-9.3)', function () {
    Mail::fake();
    [$order, $ticketType, $payment] = pendingOrderWithPayment(quantity: 2);
    $payload = completedSessionPayload($order, $payment);

    $this->postJson(route('stripe.webhook'), $payload)->assertOk();
    $this->postJson(route('stripe.webhook'), $payload)->assertOk();
    $this->postJson(route('stripe.webhook'), $payload)->assertOk();

    expect($order->fresh()->tickets()->count())->toBe(2)
        ->and($ticketType->fresh()->remaining())->toBe(8);
    Mail::assertSent(OrderConfirmationMail::class, 1);
});

it('does not treat a browser return to the success page as payment (YC-9.2)', function () {
    [$order] = pendingOrderWithPayment();

    // Người dùng chỉ mở trang success, chưa có webhook.
    $this->actingAs($order->user)->get(route('orders.show', $order).'?checkout=success')->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_PENDING)
        ->and($order->fresh()->tickets()->count())->toBe(0);
});

it('publishes PaymentSucceeded across modules so Ticketing can react (QĐ-3.5)', function () {
    EventFacade::fake([PaymentSucceeded::class]);
    [$order, , $payment] = pendingOrderWithPayment();

    $this->postJson(route('stripe.webhook'), completedSessionPayload($order, $payment))->assertOk();

    EventFacade::assertDispatched(
        PaymentSucceeded::class,
        fn (PaymentSucceeded $event): bool => $event->orderId === $order->id,
    );
});
