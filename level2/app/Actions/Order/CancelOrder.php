<?php

namespace App\Actions\Order;

use App\Models\Order;

/**
 * Hủy đơn còn đang chờ thanh toán; vé đã giữ được trả lại một cách tự nhiên
 * vì đơn đã hủy không còn được tính là đang giữ (YC-8.4, xem
 * TicketType::reservedQuantity()). Không đụng vào đơn đã ở trạng thái cuối.
 */
class CancelOrder
{
    public function handle(Order $order): void
    {
        if ($order->isPending()) {
            $order->update(['status' => Order::STATUS_CANCELLED, 'expires_at' => null]);
        }
    }
}
