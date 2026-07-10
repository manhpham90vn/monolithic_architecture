<?php

namespace Ticketing\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Ticketing\Application\ExpireStaleOrdersHandler;

#[Signature('orders:expire')]
#[Description('Đưa các đơn chờ thanh toán quá 15 phút sang trạng thái hết hạn và trả lại vé đã giữ (YC-9.1).')]
class ExpireStaleOrdersCommand extends Command
{
    /**
     * Command chỉ là lối vào CLI; nghiệp vụ nằm trong use-case của tầng
     * Application — cùng một handler dùng được từ scheduler, HTTP hay test.
     */
    public function handle(ExpireStaleOrdersHandler $expireStaleOrders): int
    {
        $expired = $expireStaleOrders->handle();

        $this->info("Đã cho hết hạn {$expired} đơn.");

        return self::SUCCESS;
    }
}
