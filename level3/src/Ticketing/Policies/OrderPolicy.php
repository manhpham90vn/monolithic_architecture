<?php

namespace Ticketing\Policies;

use App\Models\User;
use Ticketing\Models\Order;

class OrderPolicy
{
    /**
     * Người dùng chỉ xem được đơn của chính mình.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id;
    }
}
