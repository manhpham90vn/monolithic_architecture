<?php

use Ticketing\Domain\Order\IssuedTicket;
use Ticketing\Domain\Order\OrderId;
use Ticketing\Domain\Order\OrderStatus;
use Ticketing\Domain\Ticket\TicketId;
use Ticketing\Domain\Ticket\TicketStatus;

/*
| Các Value Object/enum nhỏ của Domain — POPO thuần.
*/

it('builds an OrderId and compares by value', function () {
    expect((new OrderId(42))->value)->toBe(42)
        ->and((new OrderId(42))->equals(new OrderId(42)))->toBeTrue()
        ->and((new OrderId(42))->equals(new OrderId(43)))->toBeFalse();
});

it('rejects a non-positive OrderId', function () {
    new OrderId(0);
})->throws(InvalidArgumentException::class);

it('rejects a non-positive TicketId', function () {
    new TicketId(-5);
})->throws(InvalidArgumentException::class);

it('treats only Pending as a non-final order status (§9)', function () {
    expect(OrderStatus::Pending->isFinal())->toBeFalse()
        ->and(OrderStatus::Paid->isFinal())->toBeTrue()
        ->and(OrderStatus::Expired->isFinal())->toBeTrue()
        ->and(OrderStatus::Cancelled->isFinal())->toBeTrue();
});

it('maps order statuses to their stored values', function () {
    expect(OrderStatus::Pending->value)->toBe('pending')
        ->and(OrderStatus::Paid->value)->toBe('paid')
        ->and(OrderStatus::Expired->value)->toBe('expired')
        ->and(OrderStatus::Cancelled->value)->toBe('cancelled');
});

it('maps ticket statuses to their stored values (§11)', function () {
    expect(TicketStatus::Issued->value)->toBe('issued')
        ->and(TicketStatus::Used->value)->toBe('used');
});

it('carries an issued ticket token and its type', function () {
    $ticket = new IssuedTicket('tok-1', 7, 'Vé VIP');

    expect($ticket->token)->toBe('tok-1')
        ->and($ticket->ticketTypeId)->toBe(7)
        ->and($ticket->ticketTypeName)->toBe('Vé VIP');
});
