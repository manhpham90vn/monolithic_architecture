<?php

use Ticketing\Domain\Shared\Money;

/*
| Value Object Money (YC-2.2) — POPO thuần, test không cần Laravel.
*/

it('holds a yen amount', function () {
    expect(Money::yen(5000)->amount)->toBe(5000);
});

it('has a zero constructor', function () {
    expect(Money::zero()->amount)->toBe(0);
});

it('adds two amounts immutably', function () {
    $a = Money::yen(5000);
    $b = Money::yen(3000);

    expect($a->add($b)->amount)->toBe(8000)
        // bất biến: toán tử không đổi vế trái.
        ->and($a->amount)->toBe(5000);
});

it('multiplies by a quantity', function () {
    expect(Money::yen(5000)->multiply(3)->amount)->toBe(15000)
        ->and(Money::yen(5000)->multiply(0)->amount)->toBe(0);
});

it('compares by value', function () {
    expect(Money::yen(5000)->equals(Money::yen(5000)))->toBeTrue()
        ->and(Money::yen(5000)->equals(Money::yen(5001)))->toBeFalse();
});

it('cannot represent a negative amount', function () {
    Money::yen(-1);
})->throws(InvalidArgumentException::class);
