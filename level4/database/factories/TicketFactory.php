<?php

namespace Database\Factories;

use App\Models\User;
use Catalog\Models\Event;
use Catalog\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Ticketing\Infrastructure\Persistence\OrderEloquentModel as Order;
use Ticketing\Infrastructure\Persistence\TicketEloquentModel as Ticket;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'ticket_type_id' => TicketType::factory(),
            'ticket_type_name' => fake()->randomElement(['Vé thường', 'Vé VIP', 'Vé sớm']),
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
