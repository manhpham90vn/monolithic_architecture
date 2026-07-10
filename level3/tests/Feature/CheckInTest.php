<?php

use App\Models\User;
use Ticketing\Models\Ticket;

/*
| Soát vé (§11) và cổng phân quyền nhân viên (YC-4.2). Ở mức 3, việc soát đi
| qua Public API của Ticketing (CheckIn gọi TicketingApi), nhưng hành vi HTTP
| quan sát được vẫn như các mức khác.
*/

it('blocks non-scanner users from the check-in screen (YC-4.2)', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('checkin.create'))
        ->assertForbidden();
});

it('lets a scanner open the check-in screen', function () {
    $this->actingAs(User::factory()->scanner()->create())
        ->get(route('checkin.create'))
        ->assertOk();
});

it('requires login to reach the check-in screen', function () {
    $this->get(route('checkin.create'))->assertRedirect(route('login'));
});

it('validates a ticket and marks it used (YC-11.1, YC-11.2)', function () {
    $scanner = User::factory()->scanner()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($scanner)
        ->post(route('checkin.store'), ['token' => $ticket->token])
        ->assertOk()
        ->assertSee('Hợp lệ');

    expect($ticket->fresh()->status)->toBe(Ticket::STATUS_USED)
        ->and($ticket->fresh()->used_at)->not->toBeNull();
});

it('reports "đã dùng" on a second scan of the same ticket (YC-11.3)', function () {
    $scanner = User::factory()->scanner()->create();
    $ticket = Ticket::factory()->used()->create();

    $this->actingAs($scanner)
        ->post(route('checkin.store'), ['token' => $ticket->token])
        ->assertOk()
        ->assertSee('Đã dùng');
});

it('reports a nonexistent ticket', function () {
    $scanner = User::factory()->scanner()->create();

    $this->actingAs($scanner)
        ->post(route('checkin.store'), ['token' => 'khong-ton-tai'])
        ->assertOk()
        ->assertSee('không tồn tại');
});
