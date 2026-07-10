<?php

use App\Models\User;
use Catalog\Models\Event;
use Ticketing\Infrastructure\Persistence\TicketEloquentModel as Ticket;

/*
| "Vé của tôi" (YC-10.2) và phân quyền tải mã QR (chỉ vé của chính mình).
*/

beforeEach(function () {
    $this->event = Event::factory()->create(['title' => 'Tech Expo']);
    $this->user = User::factory()->create();
});

it('shows the buyer their purchased tickets grouped by event (YC-10.2)', function () {
    Ticket::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'ticket_type_name' => 'Vé vào cửa',
    ]);

    $this->actingAs($this->user)
        ->get(route('tickets.index'))
        ->assertOk()
        ->assertSee('Tech Expo')
        ->assertSee('Vé vào cửa');
});

it('shows an empty state when the buyer has no tickets', function () {
    $this->actingAs($this->user)
        ->get(route('tickets.index'))
        ->assertOk()
        ->assertSee('chưa có vé');
});

it('requires login for the my-tickets page', function () {
    $this->get(route('tickets.index'))->assertRedirect(route('login'));
});

it('serves the QR image of the owner’s own ticket', function () {
    $ticket = Ticket::factory()->create(['user_id' => $this->user->id, 'event_id' => $this->event->id]);

    $response = $this->actingAs($this->user)->get(route('tickets.qr', $ticket));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('image/svg+xml');
});

it('refuses to serve another user’s QR code', function () {
    $ticket = Ticket::factory()->create(['user_id' => $this->user->id, 'event_id' => $this->event->id]);
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->get(route('tickets.qr', $ticket))->assertForbidden();
});
