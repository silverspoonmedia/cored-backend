<?php

namespace Tests\Domain\Playground\Feature\Http\Controllers\Web;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Web OrderController forwards staged attachments without extension filtering.
 */
class OrderUnrestrictedAttachmentWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_forwards_staged_attachment_with_any_extension(): void
    {
        Http::fake([
            '*/api/playground/orders' => Http::response([
                'status' => 'success',
                'message' => 'ok',
                'data' => ['id' => fake()->uuid()],
            ], 201),
        ]);

        $uploadId = fake()->uuid();

        $this->actingAs(User::factory()->create(), 'api')
            ->post('/orders', [
                'customer_name' => 'Jane',
                'customer_email' => 'jane@example.com',
                'details' => [['product_name' => 'A', 'quantity' => 1, 'unit_price' => 10]],
                'staged_attachments' => [$uploadId],
            ])
            ->assertRedirect();

        Http::assertSent(function ($request) use ($uploadId) {
            return str_contains($request->url(), '/api/playground/orders')
                && ($request->data()['staged_attachments'] ?? null) === [$uploadId];
        });
    }
}
