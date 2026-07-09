<?php

use App\Actions\Order\PlaceOrder;
use App\Data\PlaceOrderData;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/*
 * Điểm được của mức 2 (QĐ-8.4): nghiệp vụ nằm trong Action nên test được
 * trực tiếp, không cần đi qua tầng HTTP.
 */

it('creates a pending order with locked prices when called directly', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create(['price' => 5000, 'quantity' => 10]);
    $user = User::factory()->create();

    $order = app(PlaceOrder::class)->handle(new PlaceOrderData(
        userId: $user->id,
        eventId: $event->id,
        quantities: [$ticketType->id => 3],
    ));

    expect($order->status)->toBe(Order::STATUS_PENDING)
        ->and($order->total_amount)->toBe(15000)
        ->and($order->items()->sole()->unit_price)->toBe(5000)
        ->and($order->expires_at)->not->toBeNull()
        ->and($ticketType->fresh()->remaining())->toBe(7);
});

it('rejects an order exceeding the remaining stock', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create(['quantity' => 2]);
    $user = User::factory()->create();

    app(PlaceOrder::class)->handle(new PlaceOrderData(
        userId: $user->id,
        eventId: $event->id,
        quantities: [$ticketType->id => 3],
    ));
})->throws(ValidationException::class);
