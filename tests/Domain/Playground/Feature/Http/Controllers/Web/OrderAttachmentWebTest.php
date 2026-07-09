<?php

namespace Tests\Domain\Playground\Feature\Http\Controllers\Web;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Web order + upload controllers forward JSON to the playground API.
 */
class OrderAttachmentWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/api/playground/orders*' => Http::response([
                'status' => 'success',
                'message' => 'ok',
                'data' => ['id' => 'x'],
            ], 200),
            '*/api/playground/attachments/uploads*' => Http::response([
                'status' => 'success',
                'message' => 'ok',
                'data' => ['upload_id' => 'upload-1'],
            ], 201),
        ]);
    }

    public function test_update_forwards_staged_attachments_as_json(): void
    {
        $admin = $this->actingWithApiGuard(User::factory()->create());

        $admin->put('/orders/'.fake()->uuid(), [
            'customer_name' => 'Jane',
            'customer_email' => 'jane@example.com',
            'details' => [['product_name' => 'A', 'quantity' => 1, 'unit_price' => 10]],
            'attachments_managed' => '1',
            'staged_attachments' => [fake()->uuid()],
        ]);

        Http::assertSent(function ($request) {
            $headers = $request->headers();
            $body = $request->data();

            return str_contains($request->url(), '/api/playground/orders')
                && in_array('application/json', $headers['Content-Type'] ?? [], true)
                && ($body['attachments_managed'] ?? null) === '1'
                && is_array($body['staged_attachments'] ?? null);
        });
    }

    public function test_store_forwards_json_without_multipart(): void
    {
        $admin = $this->actingWithApiGuard(User::factory()->create());

        $admin->post('/orders', [
            'customer_name' => 'Jane',
            'customer_email' => 'jane@example.com',
            'details' => [['product_name' => 'A', 'quantity' => 1, 'unit_price' => 10]],
            'staged_attachments' => [fake()->uuid()],
        ]);

        Http::assertSent(function ($request) {
            $headers = $request->headers();

            return str_contains($request->url(), '/api/playground/orders')
                && in_array('application/json', $headers['Content-Type'] ?? [], true)
                && is_array($request->data()['staged_attachments'] ?? null);
        });
    }

    public function test_upload_init_proxies_to_playground_api(): void
    {
        $admin = $this->actingWithApiGuard(User::factory()->create());

        $admin->postJson('/orders/attachments/uploads', [
            'name' => 'invoice.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
        ])->assertCreated();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/playground/attachments/uploads')
            && $request->method() === 'POST');
    }

    private function actingWithApiGuard(User $user): static
    {
        return $this->actingAs($user, 'api');
    }
}
