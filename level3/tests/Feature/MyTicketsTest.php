<?php

use App\Models\User;
use Ticketing\Models\Ticket;

/*
| "Vé của tôi" (YC-10.2) và phân quyền tải mã QR (chỉ vé của chính mình).
*/

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

it('shows an empty state when the buyer has no tickets', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('tickets.index'))
        ->assertOk()
        ->assertSee('chưa có vé');
});

it('requires login for the my-tickets page', function () {
    $this->get(route('tickets.index'))->assertRedirect(route('login'));
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
