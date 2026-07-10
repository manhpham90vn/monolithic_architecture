<?php

use Ticketing\Domain\Exception\TicketAlreadyUsed;
use Ticketing\Domain\Ticket\Ticket;
use Ticketing\Domain\Ticket\TicketId;
use Ticketing\Domain\Ticket\TicketStatus;

/*
| Bất biến soát vé (§11) — cũng chạy không cần DB/Laravel (QĐ-4.4).
*/

function issuedTicket(?TicketStatus $status = null, ?DateTimeImmutable $usedAt = null): Ticket
{
    return Ticket::reconstitute(
        id: new TicketId(1),
        token: 'tok-1',
        ticketTypeId: 7,
        ticketTypeName: 'Vé thường',
        eventId: 10,
        userId: 5,
        status: $status ?? TicketStatus::Issued,
        usedAt: $usedAt,
    );
}

it('exposes the reconstituted attributes', function () {
    $ticket = issuedTicket();

    expect($ticket->id()->value)->toBe(1)
        ->and($ticket->token())->toBe('tok-1')
        ->and($ticket->ticketTypeId())->toBe(7)
        ->and($ticket->ticketTypeName())->toBe('Vé thường')
        ->and($ticket->eventId())->toBe(10)
        ->and($ticket->userId())->toBe(5)
        ->and($ticket->status())->toBe(TicketStatus::Issued)
        ->and($ticket->isUsed())->toBeFalse()
        ->and($ticket->usedAt())->toBeNull();
});

it('marks an issued ticket as used on check-in (YC-11.2)', function () {
    $ticket = issuedTicket();

    $ticket->checkIn(new DateTimeImmutable('2026-07-10 19:30:00'));

    expect($ticket->isUsed())->toBeTrue()
        ->and($ticket->status())->toBe(TicketStatus::Used)
        ->and($ticket->usedAt()?->format('H:i'))->toBe('19:30');
});

it('refuses a second check-in of the same ticket (YC-11.3)', function () {
    $ticket = issuedTicket();
    $ticket->checkIn(new DateTimeImmutable);

    $ticket->checkIn(new DateTimeImmutable);
})->throws(TicketAlreadyUsed::class);

it('refuses check-in on a ticket reconstituted as already used (YC-11.3)', function () {
    $ticket = issuedTicket(TicketStatus::Used, new DateTimeImmutable('2026-07-10 18:00:00'));

    expect($ticket->isUsed())->toBeTrue();

    $ticket->checkIn(new DateTimeImmutable);
})->throws(TicketAlreadyUsed::class);
