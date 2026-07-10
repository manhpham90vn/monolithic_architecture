<?php

use App\Models\User;
use Catalog\Models\Event;
use Catalog\Models\TicketType;
use Ticketing\Infrastructure\Persistence\OrderEloquentModel as Order;

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

    // Vé đang giữ được Catalog tính là không còn bán được (YC-8.2) — qua
    // bộ đếm của chính Catalog, không JOIN sang bảng orders (QĐ-3.7).
    expect($ticketType->fresh()->remaining())->toBe(7)
        ->and($ticketType->fresh()->reserved_count)->toBe(3);
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

it('rejects ticket types that belong to another event (YC-7.1)', function () {
    $otherEventType = TicketType::factory()->create(['quantity' => 10]);

    $this->actingAs($this->user)
        ->post(route('orders.store', $this->event), ['quantities' => [$otherEventType->id => 1]])
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

    // Đặt đơn thật để Catalog giữ vé, rồi cho thời gian trôi quá 15 phút.
    $this->actingAs($this->user)
        ->post(route('orders.store', $this->event), ['quantities' => [$ticketType->id => 5]])
        ->assertRedirect();

    expect($ticketType->fresh()->remaining())->toBe(0);

    $this->travel(16)->minutes();

    $this->artisan('orders:expire')->assertSuccessful();

    expect(Order::sole()->status)->toBe(Order::STATUS_EXPIRED)
        ->and($ticketType->fresh()->remaining())->toBe(5)
        ->and($ticketType->fresh()->reserved_count)->toBe(0);
});

it('lets a user cancel their own pending order and release the hold (YC-8.4)', function () {
    $ticketType = TicketType::factory()->for($this->event)->create(['quantity' => 5]);

    $this->actingAs($this->user)
        ->post(route('orders.store', $this->event), ['quantities' => [$ticketType->id => 2]])
        ->assertRedirect();

    $order = Order::sole();
    expect($ticketType->fresh()->remaining())->toBe(3);

    $this->actingAs($this->user)->post(route('orders.cancel', $order))->assertRedirect();

    expect($order->fresh()->status)->toBe(Order::STATUS_CANCELLED)
        ->and($ticketType->fresh()->remaining())->toBe(5);
});
