<?php

namespace Database\Factories;

use App\Domain\Playground\Models\Order;
use App\Domain\Playground\Support\Orders\OrderAttachmentStorage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
        $subtotal = fake()->randomFloat(2, 50, 5000);
        $discount = fake()->randomFloat(2, 0, 50);
        $tax = round($subtotal * 0.1, 2);

        return [
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->optional()->phoneNumber(),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'grand_total' => max($subtotal - $discount + $tax, 0),
            'status' => fake()->randomElement(['pending', 'processing', 'completed', 'cancelled']),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed']);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    /**
     * Seed the order with related attachment rows (no real files on disk).
     */
    public function withAttachments(int $count = 2): static
    {
        return $this->afterCreating(function (Order $order) use ($count): void {
            $order->attachments()->createMany(
                collect(range(1, $count))
                    ->map(fn (int $i): array => [
                        'disk' => OrderAttachmentStorage::DISK,
                        'path' => 'orders/attachments/file-'.$i.'-'.fake()->uuid().'.pdf',
                        'name' => 'file-'.$i.'.pdf',
                        'size' => fake()->numberBetween(1000, 500000),
                        'mime' => 'application/pdf',
                    ])
                    ->all(),
            );
        });
    }
}
