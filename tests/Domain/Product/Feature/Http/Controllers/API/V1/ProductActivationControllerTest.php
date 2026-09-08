<?php

namespace Tests\Domain\Product\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Support\Product\Enums\ProductActivationState;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ProductActivationControllerTest extends TestCase
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

    public function test_index_returns_own_activations(): void
    {
        $user = $this->userWithRole('User');
        $this->service->activateImplicit($user, 'Archivd');

        $response = $this->actingAsJwt($user)
            ->getJson('/api/v1/product-activations')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $response);
        $this->assertSame('Archivd', $response[0]['product_key']);
        $this->assertSame(ProductActivationState::Active->value, $response[0]['state']);
    }

    public function test_show_returns_own_activation(): void
    {
        $user = $this->userWithRole('User');
        $this->service->activateImplicit($user, 'Archivd');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/product-activations/Archivd')
            ->assertOk()
            ->assertJsonPath('data.product_key', 'Archivd');
    }

    public function test_show_returns_404_when_missing(): void
    {
        $user = $this->userWithRole('User');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/product-activations/Archivd')
            ->assertNotFound();
    }

    public function test_unauthenticated_blocked(): void
    {
        $this->getJson('/api/v1/product-activations')
            ->assertUnauthorized();
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
