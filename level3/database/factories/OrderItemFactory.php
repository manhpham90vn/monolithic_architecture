<?php

namespace Database\Factories;

use Catalog\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Ticketing\Models\Order;
use Ticketing\Models\OrderItem;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'ticket_type_id' => TicketType::factory(),
            'ticket_type_name' => fake()->randomElement(['Vé thường', 'Vé VIP', 'Vé sớm']),
            'quantity' => fake()->numberBetween(1, 4),
            'unit_price' => fake()->numberBetween(1000, 20000),
        ];
    }
}
