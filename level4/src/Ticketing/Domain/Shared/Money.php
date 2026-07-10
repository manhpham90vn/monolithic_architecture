<?php

namespace Ticketing\Domain\Shared;

use InvalidArgumentException;

/**
 * Value Object tiền tệ — số nguyên yên (YC-2.2). POPO thuần, không biết
 * Laravel tồn tại (QĐ-4.1): trạng thái không hợp lệ (tiền âm) không thể
 * biểu diễn được.
 */
final readonly class Money
{
    private function __construct(public readonly int $amount)
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Số tiền không được âm.');
        }
    }

    public static function yen(int $amount): self
    {
        return new self($amount);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        return new self($this->amount + $other->amount);
    }

    public function multiply(int $quantity): self
    {
        return new self($this->amount * $quantity);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount;
    }
}
