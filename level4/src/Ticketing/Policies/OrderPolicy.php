<?php

namespace Ticketing\Policies;

use App\Models\User;
use Ticketing\Infrastructure\Persistence\OrderEloquentModel;

class OrderPolicy
{
    /**
     * Người dùng chỉ xem được đơn của chính mình.
     */
    public function view(User $user, OrderEloquentModel $order): bool
    {
        return $user->id === $order->user_id;
    }
}
