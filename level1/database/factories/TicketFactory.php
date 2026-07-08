<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'ticket_type_id' => TicketType::factory(),
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'token' => Str::ulid()->toBase32(),
            'status' => Ticket::STATUS_ISSUED,
            'used_at' => null,
        ];
    }

    public function used(): static
    {
        return $this->state(fn (): array => [
            'status' => Ticket::STATUS_USED,
            'used_at' => now(),
        ]);
    }
}
