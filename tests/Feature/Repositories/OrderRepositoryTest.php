<?php

namespace Tests\Feature\Repositories;

use App\Models\Order;
use App\Repositories\Eloquent\OrderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Throwable;

class OrderRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function master(): array
    {
        return [
            'order_number' => 'ORD-TEST-0001',
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => null,
            'subtotal' => 100,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => 100,
            'status' => 'pending',
            'notes' => null,
        ];
    }

    public function test_create_with_details_persists_both_tables(): void
    {
        $repo = app(OrderRepository::class);

        $order = $repo->createWithDetails($this->master(), [
            ['product_name' => 'A', 'quantity' => 1, 'unit_price' => 100, 'discount' => 0, 'subtotal' => 100, 'notes' => null],
        ]);

        $this->assertDatabaseCount('order_master', 1);
        $this->assertDatabaseCount('order_detail', 1);
        $this->assertCount(1, $order->details);
    }

    public function test_create_rolls_back_master_when_a_detail_insert_fails(): void
    {
        $repo = app(OrderRepository::class);

        try {
            $repo->createWithDetails($this->master(), [
                // Missing required `quantity`/`subtotal` NOT NULL columns triggers a
                // DB error mid-transaction; the master insert must roll back too.
                ['product_name' => 'A', 'quantity' => 1, 'unit_price' => 100, 'discount' => 0, 'subtotal' => 100, 'notes' => null],
                ['product_name' => null, 'quantity' => null, 'unit_price' => null, 'discount' => 0, 'subtotal' => null, 'notes' => null],
            ]);
            $this->fail('Expected the detail insert to throw.');
        } catch (Throwable) {
            // expected
        }

        // No orphaned master, no partial detail rows.
        $this->assertDatabaseCount('order_master', 0);
        $this->assertDatabaseCount('order_detail', 0);
    }

    public function test_update_with_details_replaces_lines_atomically(): void
    {
        $repo = app(OrderRepository::class);

        $order = $repo->createWithDetails($this->master(), [
            ['product_name' => 'Old1', 'quantity' => 1, 'unit_price' => 50, 'discount' => 0, 'subtotal' => 50, 'notes' => null],
            ['product_name' => 'Old2', 'quantity' => 1, 'unit_price' => 50, 'discount' => 0, 'subtotal' => 50, 'notes' => null],
        ]);

        $repo->updateWithDetails($order->id, $this->master(), [
            ['product_name' => 'New', 'quantity' => 2, 'unit_price' => 30, 'discount' => 0, 'subtotal' => 60, 'notes' => null],
        ]);

        $this->assertDatabaseCount('order_detail', 1);
        $this->assertDatabaseHas('order_detail', ['product_name' => 'New']);
        $this->assertDatabaseMissing('order_detail', ['product_name' => 'Old1']);
    }

    public function test_update_with_details_preserves_kept_line_ids(): void
    {
        $repo = app(OrderRepository::class);

        $order = $repo->createWithDetails($this->master(), [
            ['product_name' => 'Keep', 'quantity' => 1, 'unit_price' => 50, 'discount' => 0, 'subtotal' => 50, 'notes' => null],
            ['product_name' => 'Drop', 'quantity' => 1, 'unit_price' => 50, 'discount' => 0, 'subtotal' => 50, 'notes' => null],
        ]);

        $keepId = $order->details->firstWhere('product_name', 'Keep')->id;
        $dropId = $order->details->firstWhere('product_name', 'Drop')->id;

        $repo->updateWithDetails($order->id, $this->master(), [
            // Same id → updated in place, UUID preserved.
            ['id' => $keepId, 'product_name' => 'Keep edited', 'quantity' => 2, 'unit_price' => 50, 'discount' => 0, 'subtotal' => 100, 'notes' => null],
            // No id → brand new line.
            ['product_name' => 'Fresh', 'quantity' => 1, 'unit_price' => 10, 'discount' => 0, 'subtotal' => 10, 'notes' => null],
        ]);

        // Kept line keeps its id and gets the new content.
        $this->assertDatabaseHas('order_detail', ['id' => $keepId, 'product_name' => 'Keep edited', 'quantity' => 2]);
        // Dropped line is gone.
        $this->assertDatabaseMissing('order_detail', ['id' => $dropId]);
        $this->assertDatabaseCount('order_detail', 2);
    }

    public function test_delete_with_details_removes_both(): void
    {
        $repo = app(OrderRepository::class);

        $order = $repo->createWithDetails($this->master(), [
            ['product_name' => 'A', 'quantity' => 1, 'unit_price' => 100, 'discount' => 0, 'subtotal' => 100, 'notes' => null],
        ]);

        $repo->deleteWithDetails($order->id);

        $this->assertDatabaseCount('order_master', 0);
        $this->assertDatabaseCount('order_detail', 0);
        $this->assertNull(Order::find($order->id));
    }
}
