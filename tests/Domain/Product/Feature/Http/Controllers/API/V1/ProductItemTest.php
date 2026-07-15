<?php

namespace Tests\Domain\Product\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Product\Models\Item;
use Database\Seeders\ProductRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

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

    public function test_admin_with_core_pivot_can_crud_items(): void
    {
        $admin = $this->userWithRole('Admin');

        $create = $this->actingAsJwt($admin)
            ->postJson('/api/v1/product/items', [
                'sku' => 'sku-001',
                'name' => 'Sample Widget',
                'description' => 'First product item',
                'unit_price_cents' => 1500,
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'SKU-001')
            ->assertJsonPath('data.name', 'Sample Widget')
            ->assertJsonPath('data.status', 'active');

        $id = $create->json('data.id');
        $this->assertNotEmpty($id);

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/product/items')
            ->assertOk()
            ->assertJsonFragment(['sku' => 'SKU-001']);

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/product/items/'.$id)
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->actingAsJwt($admin)
            ->putJson('/api/v1/product/items/'.$id, [
                'name' => 'Updated Widget',
                'unit_price_cents' => 2000,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Widget')
            ->assertJsonPath('data.unit_price_cents', 2000);

        $this->actingAsJwt($admin)
            ->deleteJson('/api/v1/product/items/'.$id)
            ->assertOk();

        $this->assertSoftDeleted('product_items', ['id' => $id]);
    }

    public function test_product_viewer_can_list_but_not_create(): void
    {
        $viewer = $this->userWithRole('Product Viewer');

        Item::query()->create([
            'sku' => 'VIEW-1',
            'name' => 'Visible',
            'unit_price_cents' => 100,
            'status' => 'draft',
        ]);

        $this->actingAsJwt($viewer)
            ->getJson('/api/v1/product/items')
            ->assertOk()
            ->assertJsonFragment(['sku' => 'VIEW-1']);

        $this->actingAsJwt($viewer)
            ->postJson('/api/v1/product/items', [
                'sku' => 'NOPE-1',
                'name' => 'Blocked',
                'unit_price_cents' => 50,
                'status' => 'draft',
            ])
            ->assertForbidden();
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = $this->userWithRole('User');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/product/items')
            ->assertForbidden();
    }

    public function test_duplicate_sku_returns_business_rule_error(): void
    {
        $admin = $this->userWithRole('Admin');

        Item::query()->create([
            'sku' => 'DUP-1',
            'name' => 'Existing',
            'unit_price_cents' => 10,
            'status' => 'draft',
        ]);

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/product/items', [
                'sku' => 'dup-1',
                'name' => 'Collision',
                'unit_price_cents' => 20,
                'status' => 'draft',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', __('api/product-items.duplicate_sku'));
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

        // Same numeric level as core Root is allowed under product kind.
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
