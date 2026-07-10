<?php

namespace Ticketing\Domain\Order;

use InvalidArgumentException;

/**
 * Định danh đơn — Value Object. Đơn chưa lưu chưa có OrderId (aggregate
 * giữ ?OrderId); sau khi Repository lưu mới có định danh dương.
 */
final readonly class OrderId
{
    public function __construct(public readonly int $value)
    {
        if ($value < 1) {
            throw new InvalidArgumentException('OrderId phải là số nguyên dương.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
