<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_id' => Event::factory(),
            'status' => Order::STATUS_PENDING,
            'total_amount' => fake()->numberBetween(1000, 50000),
            'expires_at' => now()->addMinutes(15),
            'paid_at' => null,
            'stripe_session_id' => null,
            'stripe_payment_intent' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
            'expires_at' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => Order::STATUS_PENDING,
            'expires_at' => now()->subMinute(),
        ]);
    }
}
