<?php

namespace Ticketing\Policies;

use App\Models\User;
use Ticketing\Infrastructure\Persistence\TicketEloquentModel;

class TicketPolicy
{
    /**
     * Người dùng chỉ xem/tải QR vé của chính mình.
     */
    public function view(User $user, TicketEloquentModel $ticket): bool
    {
        return $user->id === $ticket->user_id;
    }
}
