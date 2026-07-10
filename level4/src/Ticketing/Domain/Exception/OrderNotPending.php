<?php

namespace Ticketing\Domain\Exception;

use DomainException;
use Ticketing\Domain\Order\OrderStatus;

/**
 * Bất biến máy trạng thái §9: chỉ đơn đang chờ thanh toán mới chuyển được
 * sang paid/expired/cancelled. Chặn mọi chuyển trạng thái không hợp lệ
 * ngay trong aggregate thay vì rải `->update(['status' => ...])` khắp nơi.
 */
class OrderNotPending extends DomainException
{
    public static function is(OrderStatus $status): self
    {
        return new self("Thao tác chỉ hợp lệ với đơn đang chờ thanh toán (hiện: {$status->value}).");
    }
}
