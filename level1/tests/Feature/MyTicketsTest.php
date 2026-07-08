<?php

use App\Models\Ticket;
use App\Models\User;

it('shows only the current user\'s tickets (YC-10.2)', function () {
    $user = User::factory()->create();
    $mine = Ticket::factory()->for($user)->create();
    $someoneElse = Ticket::factory()->create();

    $this->actingAs($user)
        ->get(route('tickets.index'))
        ->assertOk()
        ->assertSee($mine->token)
        ->assertDontSee($someoneElse->token);
});

it('serves a QR image for the owner and forbids others', function () {
    $owner = User::factory()->create();
    $ticket = Ticket::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->get(route('tickets.qr', $ticket))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml');

    $this->actingAs(User::factory()->create())
        ->get(route('tickets.qr', $ticket))
        ->assertForbidden();
});
