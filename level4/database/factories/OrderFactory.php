<?php

namespace Database\Factories;

use App\Models\User;
use Catalog\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Ticketing\Infrastructure\Persistence\OrderEloquentModel as Order;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // Chỉ là ID tham chiếu sang Catalog (QĐ-3.7); factory test được
            // phép dựng dữ liệu của nhiều module.
            'event_id' => Event::factory(),
            'status' => Order::STATUS_PENDING,
            'total_amount' => fake()->numberBetween(1000, 50000),
            'expires_at' => now()->addMinutes(15),
            'paid_at' => null,
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
