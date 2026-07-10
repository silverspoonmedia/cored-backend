<?php

namespace Tests\Domain\Playground\Feature\Http\Controllers\API;

use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\Playground\Models\Order;
use App\Domain\Playground\Models\OrderDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaygroundOrderCrudTest extends TestCase
{
    use RefreshDatabase;

    private string $paymentGatewayId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentGatewayId = PaymentGateway::query()->create([
            'name' => 'midtrans',
            'display_name' => 'Midtrans',
            'is_active' => true,
            'priority' => 10,
        ])->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '0812345678',
            'discount' => 10,
            'tax' => 5,
            'status' => 'pending',
            'payment_gateway_id' => $this->paymentGatewayId,
            'details' => [
                ['product_name' => 'Widget', 'quantity' => 2, 'unit_price' => 100, 'discount' => 0],
                ['product_name' => 'Gadget', 'quantity' => 1, 'unit_price' => 50, 'discount' => 5],
            ],
        ], $overrides);
    }

    public function test_index_lists_orders(): void
    {
        Order::factory()->count(2)->create();

        $this->getJson('/api/playground/orders')
            ->assertOk()
            ->assertJson(['status' => 'success', 'message' => 'Orders retrieved successfully.'])
            ->assertJsonCount(2, 'data');
    }

    public function test_show_returns_order_with_details(): void
    {
        $order = Order::factory()->create();
        OrderDetail::factory()->count(3)->create(['order_master_id' => $order->id]);

        $this->getJson('/api/playground/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonCount(3, 'data.details');
    }

    public function test_store_creates_master_and_details_atomically(): void
    {
        $response = $this->postJson('/api/playground/orders', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('data.customer_name', 'Jane Doe')
            // subtotal = (2*100-0) + (1*50-5) = 245
            ->assertJsonPath('data.subtotal', 245)
            // grand_total = 245 - 10 + 5 = 240
            ->assertJsonPath('data.grand_total', 240)
            ->assertJsonCount(2, 'data.details');

        $this->assertDatabaseCount('order_master', 1);
        $this->assertDatabaseCount('order_detail', 2);
    }

    public function test_store_requires_at_least_one_detail_line(): void
    {
        $response = $this->postJson('/api/playground/orders', $this->validPayload(['details' => []]));

        // Empty array trips the Form Request (details required, min:1).
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['details']);

        $this->assertDatabaseCount('order_master', 0);
    }

    public function test_store_requires_payment_gateway_id(): void
    {
        $payload = $this->validPayload();
        unset($payload['payment_gateway_id']);

        $response = $this->postJson('/api/playground/orders', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_gateway_id']);

        $this->assertDatabaseCount('order_master', 0);
    }

    public function test_store_validates_detail_line_fields(): void
    {
        $response = $this->postJson('/api/playground/orders', $this->validPayload([
            'details' => [['product_name' => '', 'quantity' => 0, 'unit_price' => -5]],
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'details.0.product_name',
                'details.0.quantity',
                'details.0.unit_price',
            ]);
    }

    public function test_update_replaces_detail_lines(): void
    {
        $created = $this->postJson('/api/playground/orders', $this->validPayload())->json('data');

        $response = $this->putJson('/api/playground/orders/'.$created['id'], $this->validPayload([
            'customer_name' => 'John Roe',
            'details' => [
                ['product_name' => 'Replacement', 'quantity' => 4, 'unit_price' => 25, 'discount' => 0],
            ],
        ]));

        $response->assertOk()
            ->assertJsonPath('data.customer_name', 'John Roe')
            ->assertJsonPath('data.subtotal', 100)
            ->assertJsonCount(1, 'data.details');

        // New lines (no id sent) fully replace the old set.
        $this->assertDatabaseCount('order_detail', 1);
    }

    public function test_update_preserves_master_and_detail_ids(): void
    {
        $created = $this->postJson('/api/playground/orders', $this->validPayload())->json('data');
        $masterId = $created['id'];
        $keepLine = $created['details'][0];
        $dropLine = $created['details'][1];

        // Edit the kept line in place (send its id), drop the second, add a third.
        $response = $this->putJson('/api/playground/orders/'.$masterId, $this->validPayload([
            'details' => [
                ['id' => $keepLine['id'], 'product_name' => 'Widget v2', 'quantity' => 3, 'unit_price' => 100, 'discount' => 0],
                ['product_name' => 'Brand New', 'quantity' => 1, 'unit_price' => 10, 'discount' => 0],
            ],
        ]));

        $response->assertOk()
            // Master id never changes.
            ->assertJsonPath('data.id', $masterId)
            ->assertJsonCount(2, 'data.details');

        // The kept line keeps its UUID and reflects the new content.
        $this->assertDatabaseHas('order_detail', [
            'id' => $keepLine['id'],
            'order_master_id' => $masterId,
            'product_name' => 'Widget v2',
            'quantity' => 3,
        ]);

        // The dropped line is gone; no row was orphaned.
        $this->assertDatabaseMissing('order_detail', ['id' => $dropLine['id']]);
        $this->assertDatabaseCount('order_detail', 2);
    }

    public function test_update_ignores_a_detail_id_from_another_order(): void
    {
        $orderA = $this->postJson('/api/playground/orders', $this->validPayload())->json('data');
        $orderB = $this->postJson('/api/playground/orders', $this->validPayload())->json('data');
        $foreignLineId = $orderB['details'][0]['id'];

        // Sending order B's line id while editing order A must NOT hijack it;
        // the unknown-to-this-order id is treated as a new line (fresh UUID).
        $response = $this->putJson('/api/playground/orders/'.$orderA['id'], $this->validPayload([
            'details' => [
                ['id' => $foreignLineId, 'product_name' => 'Sneaky', 'quantity' => 1, 'unit_price' => 10, 'discount' => 0],
            ],
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data.details');

        // Order B's line is untouched and still belongs to order B.
        $this->assertDatabaseHas('order_detail', [
            'id' => $foreignLineId,
            'order_master_id' => $orderB['id'],
        ]);

        // Order A's new line got a fresh id, not the foreign one.
        $this->assertNotSame($foreignLineId, $response->json('data.details.0.id'));
    }

    public function test_completed_order_cannot_be_updated(): void
    {
        $order = Order::factory()->completed()->create();
        OrderDetail::factory()->create(['order_master_id' => $order->id]);

        $this->putJson('/api/playground/orders/'.$order->id, $this->validPayload())
            ->assertStatus(409)
            ->assertJson(['status' => 'error']);
    }

    public function test_destroy_removes_master_and_details(): void
    {
        $order = Order::factory()->pending()->create();
        OrderDetail::factory()->count(2)->create(['order_master_id' => $order->id]);

        $this->deleteJson('/api/playground/orders/'.$order->id)
            ->assertOk()
            ->assertJson(['status' => 'success', 'message' => 'Order deleted successfully.']);

        $this->assertDatabaseCount('order_master', 0);
        $this->assertDatabaseCount('order_detail', 0);
    }

    public function test_completed_order_cannot_be_deleted(): void
    {
        $order = Order::factory()->completed()->create();

        $this->deleteJson('/api/playground/orders/'.$order->id)
            ->assertStatus(409);

        $this->assertDatabaseCount('order_master', 1);
    }

    public function test_bulk_destroy_removes_many_orders_atomically(): void
    {
        $a = Order::factory()->pending()->create();
        $b = Order::factory()->pending()->create();
        OrderDetail::factory()->count(2)->create(['order_master_id' => $a->id]);
        OrderDetail::factory()->create(['order_master_id' => $b->id]);

        $this->deleteJson('/api/playground/orders/bulk', ['ids' => [$a->id, $b->id]])
            ->assertOk()
            ->assertJson(['status' => 'success'])
            ->assertJsonPath('data.deleted', 2);

        $this->assertDatabaseCount('order_master', 0);
        $this->assertDatabaseCount('order_detail', 0);
    }

    public function test_bulk_destroy_aborts_whole_batch_when_one_order_is_completed(): void
    {
        $open = Order::factory()->pending()->create();
        $frozen = Order::factory()->completed()->create();

        $this->deleteJson('/api/playground/orders/bulk', ['ids' => [$open->id, $frozen->id]])
            ->assertStatus(409)
            ->assertJson(['status' => 'error']);

        // Nothing was deleted: the completed order freezes the entire batch.
        $this->assertDatabaseCount('order_master', 2);
    }

    public function test_bulk_destroy_validates_the_id_set(): void
    {
        $this->deleteJson('/api/playground/orders/bulk', ['ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);

        $this->deleteJson('/api/playground/orders/bulk', ['ids' => [fake()->uuid()]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.0']);
    }

    public function test_index_datatable_mode_returns_actions(): void
    {
        Order::factory()->pending()->create();

        $this->getJson('/api/playground/orders?display_mode=datatable&draw=1&start=0&length=10')
            ->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'data' => [['id', 'order_number', 'status', 'actions']],
            ])
            ->assertJsonPath('data.0.actions.0.key', 'show');
    }
}
