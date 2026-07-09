<?php

namespace Ticketing\Policies;

use App\Models\User;
use Ticketing\Models\Ticket;

class TicketPolicy
{
    /**
     * Người dùng chỉ xem/tải QR vé của chính mình.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->id === $ticket->user_id;
    }
}
