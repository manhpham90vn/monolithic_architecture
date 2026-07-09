<?php

namespace Database\Factories;

use Catalog\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'venue' => fake()->city(),
            'starts_at' => fake()->dateTimeBetween('+1 week', '+2 months'),
            'published_at' => now(),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (): array => ['published_at' => null]);
    }
}
