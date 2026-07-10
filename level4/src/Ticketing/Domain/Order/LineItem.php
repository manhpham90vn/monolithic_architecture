<?php

namespace Ticketing\Domain\Order;

use InvalidArgumentException;
use Ticketing\Domain\Shared\Money;

/**
 * Dòng đơn — Value Object bất biến. Giá và tên hạng vé được CHỐT tại thời
 * điểm tạo đơn (YC-8.5): thay đổi giá của Catalog sau đó không đụng tới
 * LineItem đã tạo. `ticketTypeId` chỉ là ID tham chiếu sang Catalog.
 */
final class LineItem
{
    public function __construct(
        public readonly int $ticketTypeId,
        public readonly string $ticketTypeName,
        public readonly int $quantity,
        public readonly Money $unitPrice,
    ) {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Số lượng của một dòng đơn phải ≥ 1.');
        }
    }

    public function subtotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }
}
