<?php

namespace Ticketing\Domain\Order;

/**
 * Máy trạng thái đơn (§9). Pending là trạng thái duy nhất chưa kết thúc;
 * Paid/Expired/Cancelled đều là trạng thái cuối.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
