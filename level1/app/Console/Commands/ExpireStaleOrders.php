<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:expire')]
#[Description('Đưa các đơn chờ thanh toán quá 15 phút sang trạng thái hết hạn và trả lại vé đã giữ (YC-9.1).')]
class ExpireStaleOrders extends Command
{
    public function handle(): int
    {
        // Chuyển trạng thái sang "hết hạn"; vé được trả lại một cách tự nhiên
        // vì đơn hết hạn không còn được tính là đang giữ (YC-8.4, xem
        // TicketType::reservedQuantity()).
        $expired = Order::query()
            ->where('status', Order::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->update([
                'status' => Order::STATUS_EXPIRED,
                'updated_at' => now(),
            ]);

        $this->info("Đã cho hết hạn {$expired} đơn.");

        return self::SUCCESS;
    }
}
