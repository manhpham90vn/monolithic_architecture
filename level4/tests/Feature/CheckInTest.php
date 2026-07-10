<?php

use App\Models\User;
use Catalog\Models\Event;
use Catalog\Models\TicketType;
use Ticketing\Application\ConfirmOrderPaidHandler;
use Ticketing\Application\PlaceOrderHandler;
use Ticketing\Contracts\CheckInStatus;
use Ticketing\Contracts\TicketingApi;
use Ticketing\Data\PlaceOrderData;
use Ticketing\Infrastructure\Persistence\TicketEloquentModel as Ticket;

/*
| Soát vé (§11) qua Public API của Ticketing và cổng phân quyền nhân viên
| (YC-4.2). Đi qua khoá DB + Catalog nên là feature test.
*/

beforeEach(function () {
    $this->event = Event::factory()->create(['title' => 'Live Concert 2026']);
    $this->ticketType = TicketType::factory()->for($this->event)->create(['name' => 'Vé VIP', 'price' => 5000, 'quantity' => 10]);
    $this->buyer = User::factory()->create(['name' => 'Người Mua']);

    // Đặt + thanh toán để có một vé đã phát hành.
    $orderId = app(PlaceOrderHandler::class)
        ->handle(new PlaceOrderData($this->buyer->id, $this->event->id, [$this->ticketType->id => 1]))
        ->id()->value;
    app(ConfirmOrderPaidHandler::class)->handle($orderId);

    $this->token = Ticket::where('order_id', $orderId)->value('token');
});

// --- Qua Public API (TicketingApi) --------------------------------------

it('checks a valid ticket in and marks it used (YC-11.1, YC-11.2)', function () {
    $result = app(TicketingApi::class)->checkIn($this->token);

    expect($result->status)->toBe(CheckInStatus::Valid)
        ->and($result->ticket->eventTitle)->toBe('Live Concert 2026')
        ->and($result->ticket->ticketTypeName)->toBe('Vé VIP')
        ->and($result->ticket->buyerName)->toBe('Người Mua')
        ->and(Ticket::where('token', $this->token)->value('status'))->toBe(Ticket::STATUS_USED);
});

it('reports a used ticket on the second scan (YC-11.3)', function () {
    app(TicketingApi::class)->checkIn($this->token);
    $second = app(TicketingApi::class)->checkIn($this->token);

    expect($second->status)->toBe(CheckInStatus::Used)
        ->and($second->ticket->usedAt)->not->toBeNull();
});

it('reports a nonexistent token', function () {
    $result = app(TicketingApi::class)->checkIn('khong-ton-tai');

    expect($result->status)->toBe(CheckInStatus::Nonexistent)
        ->and($result->ticket)->toBeNull()
        ->and($result->scannedToken)->toBe('khong-ton-tai');
});

// --- Cổng phân quyền HTTP (YC-4.2) --------------------------------------

it('lets a scanner open the check-in screen', function () {
    $scanner = User::factory()->scanner()->create();

    $this->actingAs($scanner)->get(route('checkin.create'))->assertOk();
});

it('forbids a normal buyer from the check-in screen (YC-4.2)', function () {
    $this->actingAs($this->buyer)->get(route('checkin.create'))->assertForbidden();
});

it('requires login to reach the check-in screen', function () {
    $this->get(route('checkin.create'))->assertRedirect(route('login'));
});

it('scans a ticket through the check-in form and shows the result', function () {
    $scanner = User::factory()->scanner()->create();

    $this->actingAs($scanner)
        ->post(route('checkin.store'), ['token' => $this->token])
        ->assertOk()
        ->assertSee('Hợp lệ');

    expect(Ticket::where('token', $this->token)->value('status'))->toBe(Ticket::STATUS_USED);
});
