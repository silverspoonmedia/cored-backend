<?php

namespace Tests\Domain\Product\Unit\Database\Seeders;

use App\Domain\Identity\Enums\DomainKind;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\ProductRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ProductRoleSeederTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    public function test_seeds_product_catalog_and_grants_core_admin_permissions(): void
    {
        $this->seedRolesAndPermissions();
        $this->seed(ProductRoleSeeder::class);

        $productAdmin = Role::query()->where('name', 'Product Admin')->first();
        $this->assertNotNull($productAdmin);
        $this->assertSame(DomainKind::Product, $productAdmin->domain_kind);
        $this->assertSame(100, $productAdmin->level);

        $permission = Permission::query()->where('name', 'view product item')->first();
        $this->assertNotNull($permission);
        $this->assertSame(DomainKind::Product, $permission->domain_kind);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->assertTrue($admin->can('view product item'));
        $this->assertTrue($admin->can('create product item'));
    }
}
