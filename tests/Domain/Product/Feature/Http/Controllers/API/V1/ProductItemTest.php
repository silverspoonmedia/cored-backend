<?php

namespace Tests\Domain\Product\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use Database\Seeders\ProductRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Product catalog public API is stubbed; FileHosting is the product example.
 */
class ProductItemTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(ProductRoleSeeder::class);
    }

    public function test_product_items_routes_are_stubbed(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/product/items')
            ->assertNotFound();

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/product/items', [
                'sku' => 'sku-001',
                'name' => 'Sample',
                'unit_price_cents' => 100,
                'status' => 'draft',
            ])
            ->assertNotFound();
    }

    public function test_product_role_seeder_uses_product_domain_kind(): void
    {
        $this->assertDatabaseHas('roles', [
            'name' => 'Product Admin',
            'domain_kind' => 'product',
            'level' => 100,
        ]);

        $this->assertDatabaseHas('permissions', [
            'name' => 'view product item',
            'domain_kind' => 'product',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Root',
            'domain_kind' => 'core',
            'level' => 1,
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole($roleName);

        return $user;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
