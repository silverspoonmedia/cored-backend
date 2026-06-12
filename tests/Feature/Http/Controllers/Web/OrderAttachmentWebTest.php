<?php

namespace Tests\Feature\Http\Controllers\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The web order controller is a pure forwarder to /api/playground/orders. RBAC,
 * validation, totals and the master-detail + attachment writes are covered by
 * the API's own tests; here we fake the API and assert only the proxy behaviour
 * that lives in the web layer — chiefly that uploads are forwarded as multipart
 * and the attachment fields ride along untouched.
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
        ]);
    }

    public function test_update_forwards_attachment_fields_as_multipart(): void
    {
        $admin = $this->actingWithApiGuard(User::factory()->create());

        $admin->put('/orders/'.fake()->uuid(), [
            'customer_name' => 'Jane',
            'customer_email' => 'jane@example.com',
            'details' => [['product_name' => 'A', 'quantity' => 1, 'unit_price' => 10]],
            // The form sends this marker so the API can treat an absent
            // keep-list as "keep none" (an empty array cannot be encoded over
            // multipart). The web layer just forwards it verbatim.
            'attachments_managed' => '1',
        ]);

        Http::assertSent(function ($request) {
            $body = collect($request->data());
            $field = fn (string $name) => $body->first(fn ($p) => ($p['name'] ?? null) === $name);

            $contentType = implode(';', $request->headers()['Content-Type'] ?? []);

            return str_contains($request->url(), '/api/playground/orders')
                && str_contains($contentType, 'multipart/form-data')
                // method spoofing + the marker survive the multipart channel.
                && ($field('_method')['contents'] ?? null) === 'PUT'
                && ($field('attachments_managed')['contents'] ?? null) === '1';
        });
    }

    public function test_store_forwards_uploaded_files_as_multipart(): void
    {
        $admin = $this->actingWithApiGuard(User::factory()->create());

        $admin->post('/orders', [
            'customer_name' => 'Jane',
            'customer_email' => 'jane@example.com',
            'details' => [['product_name' => 'A', 'quantity' => 1, 'unit_price' => 10]],
            'attachments' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
        ]);

        Http::assertSent(function ($request) {
            $contentType = implode(';', $request->headers()['Content-Type'] ?? []);
            $names = collect($request->data())->pluck('name');

            return str_contains($request->url(), '/api/playground/orders')
                && str_contains($contentType, 'multipart/form-data')
                && $names->contains('attachments[]');
        });
    }

    private function actingWithApiGuard(User $user): static
    {
        // The encrypted-cookie roundtrip is not reproduced by the test harness's
        // raw withCookie(), so authenticate the `api` guard directly — that is
        // what AuthenticateWithJwtCookie resolves to in a real request, and what
        // web.auth checks. Cookie→Bearer forwarding is exercised in the browser.
        return $this->actingAs($user, 'api');
    }
}
