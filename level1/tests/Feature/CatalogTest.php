<?php

use App\Models\Event;
use App\Models\TicketType;

it('shows published events but hides unpublished ones', function () {
    $published = Event::factory()->create(['title' => 'Công khai']);
    $hidden = Event::factory()->unpublished()->create(['title' => 'Bí mật']);

    $this->get(route('events.index'))
        ->assertOk()
        ->assertSee('Công khai')
        ->assertDontSee('Bí mật');
});

it('returns 404 for an unpublished event detail page', function () {
    $hidden = Event::factory()->unpublished()->create();

    $this->get(route('events.show', $hidden))->assertNotFound();
});

it('shows remaining tickets and a sold-out badge on the detail page', function () {
    $event = Event::factory()->create();
    TicketType::factory()->for($event)->create(['name' => 'Còn vé', 'quantity' => 5]);
    $soldOut = TicketType::factory()->for($event)->create(['name' => 'Sạch vé', 'quantity' => 0]);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertSee('Còn vé')
        ->assertSee('Hết vé');
});
