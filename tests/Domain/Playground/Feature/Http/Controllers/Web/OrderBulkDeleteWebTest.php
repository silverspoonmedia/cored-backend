<?php

namespace Tests\Domain\Playground\Feature\Http\Controllers\Web;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The web bulk-delete action is a pure forwarder to the playground API's
 * `DELETE /api/playground/orders/bulk` endpoint. The atomic batch delete and
 * the frozen-order rule live in the API; here we fake the API and assert only
 * the proxy behaviour: the selected ids are forwarded in the request body, and
 * the API's JSON envelope is translated for the AJAX caller.
 */
class OrderBulkDeleteWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_delete_forwards_selected_ids_to_the_api(): void
    {
        Http::fake([
            '*/api/playground/orders/bulk' => Http::response([
                'status' => 'success',
                'message' => '2 order(s) deleted successfully.',
                'data' => ['deleted' => 2],
            ], 200),
        ]);

        $admin = $this->actingWithApiGuard(User::factory()->create());
        $ids = [fake()->uuid(), fake()->uuid()];

        $admin->deleteJson('/orders/bulk', ['ids' => $ids])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        Http::assertSent(function ($request) use ($ids) {
            return str_contains($request->url(), '/api/playground/orders/bulk')
                && $request->method() === 'DELETE'
                && $request['ids'] === $ids;
        });
    }

    public function test_bulk_delete_relays_api_error(): void
    {
        Http::fake([
            '*/api/playground/orders/bulk' => Http::response([
                'status' => 'error',
                'message' => 'A completed order can no longer be modified or deleted.',
            ], 409),
        ]);

        $admin = $this->actingWithApiGuard(User::factory()->create());

        $admin->deleteJson('/orders/bulk', ['ids' => [fake()->uuid()]])
            ->assertStatus(409)
            ->assertJsonPath('status', 'error');
    }

    private function actingWithApiGuard(User $user): static
    {
        return $this->actingAs($user, 'api');
    }
}
