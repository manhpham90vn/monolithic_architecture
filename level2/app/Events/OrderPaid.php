<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Đơn vừa được xác nhận thanh toán (YC-7.4). Việc phụ (phát hành vé, gửi
 * mail) phản ứng qua listener thay vì nhét vào Action chính (QĐ-2.5).
 */
class OrderPaid
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}
}
