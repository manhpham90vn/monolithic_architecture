<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['Vé thường', 'Vé VIP', 'Vé sớm']),
            'price' => fake()->numberBetween(1000, 20000),
            'quantity' => fake()->numberBetween(10, 200),
        ];
    }
}
