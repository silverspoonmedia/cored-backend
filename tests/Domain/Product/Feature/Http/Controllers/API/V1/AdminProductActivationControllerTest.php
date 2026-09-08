<?php

namespace Tests\Domain\Product\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Support\Product\Enums\ProductActivationState;
use App\Support\Product\Models\ProductActivation;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AdminProductActivationControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ProductActivationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->service = app(ProductActivationService::class);
    }

    public function test_admin_can_list_all_activations(): void
    {
        $admin = $this->userWithRole('Admin');
        $user = $this->userWithRole('User', 'target@example.com');
        $this->service->activateImplicit($user, 'Archivd');

        $response = $this->actingAsJwt($admin)
            ->getJson('/api/v1/admin/product-activations')
            ->assertOk()
            ->json('data');

        $this->assertGreaterThanOrEqual(1, count($response));
    }

    public function test_admin_can_filter_by_product_key(): void
    {
        $admin = $this->userWithRole('Admin');
        $user = $this->userWithRole('User', 'target@example.com');
        $this->service->activateImplicit($user, 'Archivd');

        $response = $this->actingAsJwt($admin)
            ->getJson('/api/v1/admin/product-activations?product_key=Archivd')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $response);
        $this->assertSame('Archivd', $response[0]['product_key']);
    }

    public function test_admin_can_filter_by_state(): void
    {
        $admin = $this->userWithRole('Admin');
        $user = $this->userWithRole('User', 'target@example.com');
        $activation = $this->service->activateImplicit($user, 'Archivd');
        $this->service->suspend($activation);

        $suspendedResponse = $this->actingAsJwt($admin)
            ->getJson('/api/v1/admin/product-activations?state=suspended')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $suspendedResponse);
        $this->assertSame(ProductActivationState::Suspended->value, $suspendedResponse[0]['state']);
    }

    public function test_admin_can_suspend_activation(): void
    {
        $admin = $this->userWithRole('Admin');
        $user = $this->userWithRole('User', 'target@example.com');
        $activation = $this->service->activateImplicit($user, 'Archivd');

        $this->actingAsJwt($admin)
            ->postJson("/api/v1/admin/product-activations/{$activation->id}/suspend", ['reason' => 'abuse'])
            ->assertOk()
            ->assertJsonPath('data.state', ProductActivationState::Suspended->value);
    }

    public function test_admin_can_reactivate_activation(): void
    {
        $admin = $this->userWithRole('Admin');
        $user = $this->userWithRole('User', 'target@example.com');
        $activation = $this->service->activateImplicit($user, 'Archivd');
        $this->service->suspend($activation);

        $this->actingAsJwt($admin)
            ->postJson("/api/v1/admin/product-activations/{$activation->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.state', ProductActivationState::Active->value);
    }

    public function test_regular_user_cannot_access_admin_endpoints(): void
    {
        $user = $this->userWithRole('User');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/admin/product-activations')
            ->assertForbidden();
    }

    public function test_admin_without_manage_cannot_suspend(): void
    {
        $moderator = $this->userWithRole('Moderator');
        $user = $this->userWithRole('User', 'target@example.com');
        $activation = $this->service->activateImplicit($user, 'Archivd');

        $this->actingAsJwt($moderator)
            ->postJson("/api/v1/admin/product-activations/{$activation->id}/suspend")
            ->assertForbidden();
    }

    public function test_stats_endpoint_returns_state_counts(): void
    {
        $admin = $this->userWithRole('Admin');
        $user1 = $this->userWithRole('User', 'u1@example.com');
        $user2 = $this->userWithRole('User', 'u2@example.com');

        $this->service->activateImplicit($user1, 'Archivd');
        $activation2 = $this->service->activateImplicit($user2, 'Archivd');
        $this->service->suspend($activation2);

        $stats = $this->actingAsJwt($admin)
            ->getJson('/api/v1/admin/product-activations/stats')
            ->assertOk()
            ->json('data');

        $byState = collect($stats)->keyBy('state');
        $this->assertSame(1, $byState['active']['count']);
        $this->assertSame(1, $byState['suspended']['count']);
        $this->assertSame(0, $byState['expired']['count']);
    }

    public function test_products_endpoint_lists_all_products_with_summary(): void
    {
        $admin = $this->userWithRole('Admin');

        $products = $this->actingAsJwt($admin)
            ->getJson('/api/v1/admin/product-activations/products')
            ->assertOk()
            ->json('data');

        $this->assertGreaterThanOrEqual(1, count($products));
        $product = collect($products)->firstWhere('product_key', 'Archivd');
        $this->assertNotNull($product);
        $this->assertSame('implicit', $product['activation_type']);
        $this->assertSame('archivd', $product['fulfillment_target']);
        $this->assertArrayHasKey('counts', $product);
        $this->assertArrayHasKey('product_metrics', $product);
    }

    public function test_product_show_returns_summary_and_activations(): void
    {
        $admin = $this->userWithRole('Admin');
        $user = $this->userWithRole('User', 'target@example.com');
        $this->service->activateImplicit($user, 'Archivd');

        $payload = $this->actingAsJwt($admin)
            ->getJson('/api/v1/admin/product-activations/products/Archivd')
            ->assertOk()
            ->json('data');

        $this->assertSame('Archivd', $payload['summary']['product_key']);
        $this->assertGreaterThanOrEqual(1, count($payload['activations']));
        $this->assertArrayHasKey('pagination', $payload);
    }

    public function test_product_show_filters_by_state(): void
    {
        $admin = $this->userWithRole('Admin');
        $active = $this->userWithRole('User', 'active@example.com');
        $suspended = $this->userWithRole('User', 'suspended@example.com');

        $a = $this->service->activateImplicit($active, 'Archivd');
        $b = $this->service->activateImplicit($suspended, 'Archivd');
        $this->service->suspend($b);

        $activations = $this->actingAsJwt($admin)
            ->getJson('/api/v1/admin/product-activations/products/Archivd?state=suspended')
            ->assertOk()
            ->json('data.activations');

        $this->assertCount(1, $activations);
        $this->assertSame(ProductActivationState::Suspended->value, $activations[0]['state']);
    }

    public function test_product_show_unknown_product_returns_404(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/admin/product-activations/products/Nonexistent')
            ->assertNotFound();
    }

    public function test_user_detail_returns_per_user_drill_down(): void
    {
        $admin = $this->userWithRole('Admin');
        $user = $this->userWithRole('User', 'target@example.com');
        $this->service->activateImplicit($user, 'Archivd');

        $detail = $this->actingAsJwt($admin)
            ->getJson("/api/v1/admin/product-activations/products/Archivd/users/{$user->id}")
            ->assertOk()
            ->json('data');

        $this->assertSame($user->id, $detail['user_id']);
        $this->assertSame('Archivd', $detail['product_key']);
        $this->assertNotNull($detail['activation']);
        $this->assertArrayHasKey('quota', $detail);
    }

    private function userWithRole(string $role, string $email = 'admin@example.com'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => $email,
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
