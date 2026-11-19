<?php

namespace Tests\Domain\DomainAccess\Unit;

use App\Domain\Identity\Enums\DomainKind;
use App\Domain\Identity\Models\Role;
use App\Support\DomainAccess\ProductNames;
use Database\Seeders\DomainAccessSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DomainAccessSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(DomainAccessSeeder::class);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function productDomainProvider(): array
    {
        return [
            'Archivd' => ['Archivd'],
        ];
    }

    #[DataProvider('productDomainProvider')]
    public function test_beta_role_seeded_as_product_kind(string $productKey): void
    {
        $this->assertDatabaseHas('roles', [
            'name' => ProductNames::betaRole($productKey),
            'domain_kind' => DomainKind::Product->value,
            'level' => null,
        ]);
    }

    #[DataProvider('productDomainProvider')]
    public function test_access_permission_seeded_and_granted(string $productKey): void
    {
        $permission = ProductNames::accessPermission($productKey);

        $this->assertDatabaseHas('permissions', [
            'name' => $permission,
            'domain_kind' => DomainKind::Product->value,
        ]);

        $betaRole = Role::query()->where('name', ProductNames::betaRole($productKey))->firstOrFail();
        $this->assertTrue($betaRole->hasPermissionTo($permission));

        $root = Role::query()->where('name', 'Root')->firstOrFail();
        $admin = Role::query()->where('name', 'Admin')->firstOrFail();
        $this->assertTrue($root->hasPermissionTo($permission));
        $this->assertTrue($admin->hasPermissionTo($permission));
    }

    #[DataProvider('productDomainProvider')]
    public function test_visibility_row_seeded_public(string $productKey): void
    {
        $this->assertDatabaseHas('product_domain_visibilities', [
            'product_key' => $productKey,
            'visibility' => 'public',
        ]);
    }

    public function test_core_domain_access_permissions_granted_to_root_admin(): void
    {
        foreach (['Root', 'Admin'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();
            $this->assertTrue($role->hasPermissionTo('view domain access'));
            $this->assertTrue($role->hasPermissionTo('manage domain access'));
        }

        $this->assertDatabaseHas('permissions', [
            'name' => 'view domain access',
            'domain_kind' => DomainKind::Core->value,
        ]);
    }
}
