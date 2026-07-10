<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Payment\Models\Payment;
use Ticketing\Infrastructure\Persistence\OrderEloquentModel as Order;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'amount' => fake()->numberBetween(1000, 50000),
            'status' => Payment::STATUS_PENDING,
            'stripe_session_id' => 'cs_test_'.fake()->unique()->lexify('??????????'),
            'stripe_payment_intent' => null,
        ];
    }
}
