<?php

use Ticketing\Domain\Order\LineItem;
use Ticketing\Domain\Shared\Money;

/*
| Value Object LineItem — dòng đơn bất biến, chốt giá tại thời điểm tạo đơn.
*/

it('computes the subtotal as unit price times quantity (YC-8.5)', function () {
    $item = new LineItem(1, 'Vé VIP', 3, Money::yen(15000));

    expect($item->subtotal())->toBeInstanceOf(Money::class)
        ->and($item->subtotal()->amount)->toBe(45000);
});

it('keeps the locked unit price and name', function () {
    $item = new LineItem(9, 'Vé sớm', 2, Money::yen(4000));

    expect($item->ticketTypeId)->toBe(9)
        ->and($item->ticketTypeName)->toBe('Vé sớm')
        ->and($item->quantity)->toBe(2)
        ->and($item->unitPrice->amount)->toBe(4000);
});

it('rejects a non-positive quantity', function () {
    new LineItem(1, 'Vé', 0, Money::yen(5000));
})->throws(InvalidArgumentException::class);

it('allows a free line (zero unit price)', function () {
    $item = new LineItem(1, 'Vé mời', 4, Money::yen(0));

    expect($item->subtotal()->amount)->toBe(0);
});
