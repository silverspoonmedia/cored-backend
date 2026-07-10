<?php

namespace Database\Factories;

use App\Domain\Playground\Models\Order;
use App\Domain\Playground\Models\OrderDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderDetail>
 */
class OrderDetailFactory extends Factory
{
    protected $model = OrderDetail::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 10);
        $unitPrice = fake()->randomFloat(2, 5, 500);
        $discount = fake()->randomFloat(2, 0, 20);

        return [
            'order_master_id' => Order::factory(),
            'product_name' => fake()->words(3, true),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'subtotal' => max(($quantity * $unitPrice) - $discount, 0),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
