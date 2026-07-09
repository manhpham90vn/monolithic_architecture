<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;

beforeEach(function () {
    $this->event = Event::factory()->create();
    $this->user = User::factory()->create();
});

it('requires login to buy tickets', function () {
    $ticketType = TicketType::factory()->for($this->event)->create();

    $this->post(route('orders.store', $this->event), [
        'quantities' => [$ticketType->id => 1],
    ])->assertRedirect(route('login'));

    expect(Order::count())->toBe(0);
});

it('creates a pending order that holds tickets and locks the price', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['price' => 5000, 'quantity' => 10]);

    $this->actingAs($this->user)
        ->post(route('orders.store', $this->event), ['quantities' => [$ticketType->id => 3]])
        ->assertRedirect();

    $order = Order::sole();
    expect($order->status)->toBe(Order::STATUS_PENDING)
        ->and($order->total_amount)->toBe(15000)
        ->and($order->items->first()->unit_price)->toBe(5000)
        ->and($order->expires_at)->not->toBeNull();

    // Vé đang giữ được tính là không còn bán được (YC-8.2).
    expect($ticketType->fresh()->remaining())->toBe(7);
});

it('locks the price against later price changes (YC-8.5)', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['price' => 5000, 'quantity' => 10]);

    $this->actingAs($this->user)
        ->post(route('orders.store', $this->event), ['quantities' => [$ticketType->id => 2]]);

    $ticketType->update(['price' => 9999]);

    expect(Order::sole()->total_amount)->toBe(10000);
});

it('rejects more than 10 tickets per order (YC-8.1)', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['quantity' => 100]);

    $this->actingAs($this->user)
        ->post(route('orders.store', $this->event), ['quantities' => [$ticketType->id => 11]])
        ->assertSessionHasErrors('quantities');

    expect(Order::count())->toBe(0);
});

it('does not oversell when a second buyer takes the last tickets (YC-8.3)', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['quantity' => 2]);
    $second = User::factory()->create();

    // Người mua thứ nhất giữ cả 2 vé cuối.
    $this->actingAs($this->user)
        ->post(route('orders.store', $this->event), ['quantities' => [$ticketType->id => 2]])
        ->assertRedirect();

    // Người mua thứ hai bị từ chối vì hết vé.
    $this->actingAs($second)
        ->post(route('orders.store', $this->event), ['quantities' => [$ticketType->id => 1]])
        ->assertSessionHasErrors('quantities');

    expect(Order::count())->toBe(1);
});

it('releases held tickets when a pending order expires (YC-9.1)', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['quantity' => 5]);

    $order = Order::factory()->expired()->for($this->user)->for($this->event)->create();
    $order->items()->create(['ticket_type_id' => $ticketType->id, 'quantity' => 5, 'unit_price' => 1000]);

    // Trước khi hết hạn: vé vẫn bị giữ.
    // (đơn expired() có expires_at ở quá khứ nên đã không còn tính là giữ)
    $this->artisan('orders:expire')->assertSuccessful();

    expect($order->fresh()->status)->toBe(Order::STATUS_EXPIRED)
        ->and($ticketType->fresh()->remaining())->toBe(5);
});

it('lets a user cancel their own pending order and release the hold (YC-8.4)', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['quantity' => 5]);
    $order = Order::factory()->for($this->user)->for($this->event)->create();
    $order->items()->create(['ticket_type_id' => $ticketType->id, 'quantity' => 2, 'unit_price' => 1000]);

    $this->actingAs($this->user)->post(route('orders.cancel', $order))->assertRedirect();

    expect($order->fresh()->status)->toBe(Order::STATUS_CANCELLED)
        ->and($ticketType->fresh()->remaining())->toBe(5);
});
