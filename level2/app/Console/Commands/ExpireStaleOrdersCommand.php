<?php

namespace App\Console\Commands;

use App\Actions\Order\ExpireStaleOrders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:expire')]
#[Description('Đưa các đơn chờ thanh toán quá 15 phút sang trạng thái hết hạn và trả lại vé đã giữ (YC-9.1).')]
class ExpireStaleOrdersCommand extends Command
{
    /**
     * Command chỉ là lối vào CLI; nghiệp vụ nằm trong Action (QĐ-2.1) —
     * cùng một Action dùng được từ scheduler, HTTP hay test.
     */
    public function handle(ExpireStaleOrders $expireStaleOrders): int
    {
        $expired = $expireStaleOrders->handle();

        $this->info("Đã cho hết hạn {$expired} đơn.");

        return self::SUCCESS;
    }
}
