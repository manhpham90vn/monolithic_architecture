<?php

use App\Mail\OrderConfirmationMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * @return array{Order, TicketType}
 */
function pendingOrderWithSession(int $quantity = 2): array
{
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create(['quantity' => 10, 'price' => 3000]);
    $order = Order::factory()->for(User::factory())->for($event)->create([
        'total_amount' => 3000 * $quantity,
        'stripe_session_id' => 'cs_test_'.uniqid(),
    ]);
    $order->items()->create([
        'ticket_type_id' => $ticketType->id,
        'quantity' => $quantity,
        'unit_price' => 3000,
    ]);

    return [$order, $ticketType];
}

/**
 * @return array<string, mixed>
 */
function completedSessionPayload(Order $order): array
{
    return [
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => $order->stripe_session_id,
            'payment_intent' => 'pi_test_123',
            'metadata' => ['order_id' => (string) $order->id],
        ]],
    ];
}

it('marks the order paid and issues one ticket per quantity via the webhook (YC-9.2, YC-10.1)', function () {
    Mail::fake();
    [$order, $ticketType] = pendingOrderWithSession(quantity: 2);

    $this->postJson(route('stripe.webhook'), completedSessionPayload($order))->assertOk();

    $order->refresh();
    expect($order->status)->toBe(Order::STATUS_PAID)
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->tickets()->count())->toBe(2);

    Mail::assertQueued(OrderConfirmationMail::class, 1);
});

it('is idempotent: repeated webhooks do not issue extra tickets or emails (YC-9.3)', function () {
    Mail::fake();
    [$order] = pendingOrderWithSession(quantity: 2);
    $payload = completedSessionPayload($order);

    $this->postJson(route('stripe.webhook'), $payload)->assertOk();
    $this->postJson(route('stripe.webhook'), $payload)->assertOk();
    $this->postJson(route('stripe.webhook'), $payload)->assertOk();

    expect($order->fresh()->tickets()->count())->toBe(2);
    Mail::assertQueued(OrderConfirmationMail::class, 1);
});

it('defers the confirmation email to the queue so a mail failure cannot roll back the payment (YC-9.3)', function () {
    Mail::fake();
    [$order] = pendingOrderWithSession(quantity: 2);

    $response = $this->postJson(route('stripe.webhook'), completedSessionPayload($order));

    // Đơn PAID + vé đã phát hành được commit độc lập với việc gửi mail.
    $response->assertOk();
    $order->refresh();
    expect($order->status)->toBe(Order::STATUS_PAID)
        ->and($order->tickets()->count())->toBe(2);

    // Mail xác nhận được ĐẨY QUA QUEUE, không gửi đồng bộ trong webhook.
    // Nhờ vậy SMTP lỗi/timeout chỉ ảnh hưởng job trong worker (tự retry),
    // KHÔNG làm webhook trả 500 để rồi Stripe retry rơi vào idempotency và
    // vĩnh viễn mất mail.
    Mail::assertQueued(OrderConfirmationMail::class, 1);
    Mail::assertNotSent(OrderConfirmationMail::class);
});

it('does not treat a browser return to the success page as payment (YC-9.2)', function () {
    [$order] = pendingOrderWithSession();

    // Người dùng chỉ mở trang success, chưa có webhook.
    $this->actingAs($order->user)->get(route('orders.show', $order).'?checkout=success')->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_PENDING)
        ->and($order->tickets()->count())->toBe(0);
});
