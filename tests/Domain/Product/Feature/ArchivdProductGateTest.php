<?php

namespace Tests\Domain\Product\Feature;

use App\Domain\Identity\Models\User;
use App\Support\Product\Enums\ProductActivationState;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\ArchivdRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ArchivdProductGateTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ProductActivationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(ArchivdRoleSeeder::class);

        config([
            'archivd.disk' => 'local',
            'archivd.default_quota_bytes' => 1024 * 1024,
            'archivd.allowed_mime_prefixes' => ['image/', 'application/', 'text/'],
        ]);

        Storage::fake('local');
        $this->service = app(ProductActivationService::class);
    }

    public function test_user_with_active_activation_can_access_archivd(): void
    {
        $user = $this->userWithRole('User');
        $this->service->activateImplicit($user, 'Archivd');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/archivd/quota')
            ->assertOk();
    }

    public function test_user_without_activation_lazy_creates_and_passes(): void
    {
        $user = $this->userWithRole('User');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/archivd/quota')
            ->assertOk();

        $this->assertDatabaseHas('product_activations', [
            'user_id' => $user->id,
            'product_key' => 'Archivd',
            'state' => ProductActivationState::Active->value,
        ]);
    }

    public function test_user_with_suspended_activation_blocked(): void
    {
        $user = $this->userWithRole('User');
        $activation = $this->service->activateImplicit($user, 'Archivd');
        $this->service->suspend($activation);

        $this->actingAsJwt($user)
            ->getJson('/api/v1/archivd/quota')
            ->assertForbidden();
    }

    public function test_public_share_routes_not_gated(): void
    {
        $user = $this->userWithRole('User');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/archivd/public/folders/'.fake()->uuid())
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
