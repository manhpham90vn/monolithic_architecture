<?php

namespace Database\Factories;

use Catalog\Models\Event;
use Catalog\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    protected $model = TicketType::class;

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
            'reserved_count' => 0,
            'sold_count' => 0,
        ];
    }
}
