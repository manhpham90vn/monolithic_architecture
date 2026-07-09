<?php

namespace App\Actions\Order;

use App\Models\Order;

/**
 * Đưa các đơn chờ thanh toán quá 15 phút sang trạng thái hết hạn; vé được
 * trả lại vì đơn hết hạn không còn được tính là đang giữ (YC-9.1, YC-8.4,
 * xem TicketType::reservedQuantity()).
 */
class ExpireStaleOrders
{
    /**
     * @return int Số đơn vừa được cho hết hạn.
     */
    public function handle(): int
    {
        return Order::query()
            ->where('status', Order::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->update([
                'status' => Order::STATUS_EXPIRED,
                'updated_at' => now(),
            ]);
    }
}
