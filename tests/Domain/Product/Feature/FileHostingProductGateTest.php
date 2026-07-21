<?php

namespace Tests\Domain\Product\Feature;

use App\Domain\Identity\Models\User;
use App\Support\Product\Enums\ProductActivationState;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\FileHostingRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class FileHostingProductGateTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ProductActivationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(FileHostingRoleSeeder::class);

        config([
            'file-hosting.disk' => 'local',
            'file-hosting.default_quota_bytes' => 1024 * 1024,
            'file-hosting.allowed_mime_prefixes' => ['image/', 'application/', 'text/'],
        ]);

        Storage::fake('local');
        $this->service = app(ProductActivationService::class);
    }

    public function test_user_with_active_activation_can_access_file_hosting(): void
    {
        $user = $this->userWithRole('User');
        $this->service->activateImplicit($user, 'FileHosting');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/quota')
            ->assertOk();
    }

    public function test_user_without_activation_lazy_creates_and_passes(): void
    {
        $user = $this->userWithRole('User');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/quota')
            ->assertOk();

        $this->assertDatabaseHas('product_activations', [
            'user_id' => $user->id,
            'product_key' => 'FileHosting',
            'state' => ProductActivationState::Active->value,
        ]);
    }

    public function test_user_with_suspended_activation_blocked(): void
    {
        $user = $this->userWithRole('User');
        $activation = $this->service->activateImplicit($user, 'FileHosting');
        $this->service->suspend($activation);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/quota')
            ->assertForbidden();
    }

    public function test_public_share_routes_not_gated(): void
    {
        $user = $this->userWithRole('User');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/file-hosting/public/folders/'.fake()->uuid())
            ->assertNotFound();
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
