<?php

namespace Tests\Domain\Product\Feature;

use App\Domain\Identity\Models\User;
use App\Support\Product\Enums\ProductActivationState;
use App\Support\Product\Models\ProductActivation;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\FileHostingRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ProductActiveMiddlewareTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ProductActivationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(FileHostingRoleSeeder::class);
        $this->service = app(ProductActivationService::class);
    }

    public function test_active_activation_passes(): void
    {
        $user = $this->userWithRole('User');
        $this->service->activateImplicit($user, 'FileHosting');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/product-activations/FileHosting')
            ->assertOk();
    }

    public function test_missing_implicit_lazy_creates_and_passes(): void
    {
        $user = $this->userWithRole('User');

        $this->assertDatabaseMissing('product_activations', [
            'user_id' => $user->id,
            'product_key' => 'FileHosting',
        ]);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/quota')
            ->assertStatus(200);

        $this->assertDatabaseHas('product_activations', [
            'user_id' => $user->id,
            'product_key' => 'FileHosting',
            'state' => ProductActivationState::Active->value,
            'source' => 'lazy_create',
        ]);
    }

    public function test_suspended_activation_returns_403(): void
    {
        $user = $this->userWithRole('User');
        $activation = $this->service->activateImplicit($user, 'FileHosting');
        $this->service->suspend($activation);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/quota')
            ->assertForbidden();
    }

    public function test_expired_activation_returns_403(): void
    {
        $user = $this->userWithRole('User');
        $activation = $this->service->activateImplicit($user, 'FileHosting');
        $this->service->markExpired($activation);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/quota')
            ->assertForbidden();
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/file-hosting/quota')
            ->assertUnauthorized();
    }

    public function test_pending_explicit_returns_403(): void
    {
        config()->set('products.Billing', [
            'activation_type' => 'explicit',
            'fulfillment_target' => null,
        ]);

        $user = $this->userWithRole('User');
        $this->service->requestExplicit($user, 'Billing');

        $this->assertTrue($this->service->find($user->id, 'Billing') !== null);
        $this->assertSame(
            ProductActivationState::Pending,
            $this->service->find($user->id, 'Billing')->state,
        );
    }

    private function userWithRole(string $role, string $email = 'user@example.com'): User
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
