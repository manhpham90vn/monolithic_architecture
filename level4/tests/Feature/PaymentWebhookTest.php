<?php

use App\Models\User;
use Catalog\Models\Event;
use Catalog\Models\TicketType;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Mail;
use Payment\Contracts\PaymentSucceeded;
use Payment\Models\Payment;
use Ticketing\Application\PlaceOrderHandler;
use Ticketing\Data\PlaceOrderData;
use Ticketing\Infrastructure\Persistence\OrderEloquentModel as Order;
use Ticketing\Mail\OrderConfirmationMail;

/*
| Webhook Stripe end-to-end qua HTTP (YC-9.2). Bổ trợ cho PaymentConfirmationTest
| (gọi handler trực tiếp): ở đây đi trọn tầng HTTP → Payment ConfirmStripePayment
| → event PaymentSucceeded → Ticketing (ConfirmOrderPaidHandler + aggregate) →
| phát hành vé + chốt kho + email, và idempotent (YC-9.3).
*/

/**
 * @return array{int, TicketType, Payment}
 */
function pendingOrderWithPayment(int $quantity = 2): array
{
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create(['quantity' => 10, 'price' => 3000]);
    $user = User::factory()->create();

    $orderId = app(PlaceOrderHandler::class)
        ->handle(new PlaceOrderData($user->id, $event->id, [$ticketType->id => $quantity]))
        ->id()->value;

    $payment = Payment::factory()->create([
        'order_id' => $orderId,
        'amount' => 3000 * $quantity,
        'status' => Payment::STATUS_PENDING,
    ]);

    return [$orderId, $ticketType, $payment];
}

/**
 * @return array<string, mixed>
 */
function completedSessionPayload(int $orderId, Payment $payment): array
{
    return [
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => $payment->stripe_session_id,
            'payment_intent' => 'pi_test_123',
            'metadata' => ['order_id' => (string) $orderId],
        ]],
    ];
}

it('marks the order paid and issues one ticket per quantity via the webhook (YC-9.2, YC-10.1)', function () {
    Mail::fake();
    [$orderId, $ticketType, $payment] = pendingOrderWithPayment(quantity: 2);

    $this->postJson(route('stripe.webhook'), completedSessionPayload($orderId, $payment))->assertOk();

    $order = Order::find($orderId);
    expect($order->status)->toBe(Order::STATUS_PAID)
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->tickets()->count())->toBe(2)
        ->and($payment->fresh()->status)->toBe(Payment::STATUS_SUCCEEDED)
        ->and($ticketType->fresh()->remaining())->toBe(8)
        ->and($ticketType->fresh()->reserved_count)->toBe(0);

    Mail::assertSent(OrderConfirmationMail::class, 1);
});

it('is idempotent: repeated webhooks do not issue extra tickets or emails (YC-9.3)', function () {
    Mail::fake();
    [$orderId, $ticketType, $payment] = pendingOrderWithPayment(quantity: 2);
    $payload = completedSessionPayload($orderId, $payment);

    $this->postJson(route('stripe.webhook'), $payload)->assertOk();
    $this->postJson(route('stripe.webhook'), $payload)->assertOk();
    $this->postJson(route('stripe.webhook'), $payload)->assertOk();

    expect(Order::find($orderId)->tickets()->count())->toBe(2)
        ->and($ticketType->fresh()->remaining())->toBe(8);
    Mail::assertSent(OrderConfirmationMail::class, 1);
});

it('does not treat a browser return to the success page as payment (YC-9.2)', function () {
    [$orderId] = pendingOrderWithPayment();
    $order = Order::find($orderId);

    $this->actingAs($order->user)->get(route('orders.show', $order).'?checkout=success')->assertOk();

    expect(Order::find($orderId)->status)->toBe(Order::STATUS_PENDING)
        ->and(Order::find($orderId)->tickets()->count())->toBe(0);
});

it('publishes PaymentSucceeded across modules so Ticketing can react (QĐ-3.5)', function () {
    EventFacade::fake([PaymentSucceeded::class]);
    [$orderId, , $payment] = pendingOrderWithPayment();

    $this->postJson(route('stripe.webhook'), completedSessionPayload($orderId, $payment))->assertOk();

    EventFacade::assertDispatched(
        PaymentSucceeded::class,
        fn (PaymentSucceeded $event): bool => $event->orderId === $orderId,
    );
});
