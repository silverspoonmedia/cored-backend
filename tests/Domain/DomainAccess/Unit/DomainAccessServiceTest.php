<?php

namespace Tests\Domain\DomainAccess\Unit;

use App\Domain\Identity\Models\User;
use App\Support\DomainAccess\DomainAccessService;
use App\Support\DomainAccess\Enums\DomainVisibility;
use App\Support\DomainAccess\Models\ProductDomainVisibility;
use App\Support\DomainAccess\ProductNames;
use Database\Seeders\DomainAccessSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    private DomainAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(DomainAccessSeeder::class);
        $this->service = app(DomainAccessService::class);
    }

    public function test_visibility_defaults_to_public_when_no_row(): void
    {
        ProductDomainVisibility::query()->delete();

        $this->assertSame(
            DomainVisibility::Public,
            $this->service->visibilityFor('FileHosting'),
        );
    }

    public function test_set_visibility_persists_and_busts_cache(): void
    {
        $this->service->all();

        $this->service->setVisibility('FileHosting', DomainVisibility::Private);

        $this->assertSame(
            DomainVisibility::Private,
            $this->service->visibilityFor('FileHosting'),
        );
        $this->assertDatabaseHas('product_domain_visibilities', [
            'product_key' => 'FileHosting',
            'visibility' => 'private',
        ]);
    }

    public function test_set_visibility_rejects_non_product_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->setVisibility('Identity', DomainVisibility::Private);
    }

    public function test_public_domain_grants_access_to_everyone_including_guests(): void
    {
        $this->service->setVisibility('FileHosting', DomainVisibility::Public);

        $this->assertTrue($this->service->canAccess(null, 'FileHosting'));
        $this->assertTrue($this->service->canAccess($this->user('User'), 'FileHosting'));
    }

    public function test_private_domain_blocks_guest_and_plain_user(): void
    {
        $this->service->setVisibility('FileHosting', DomainVisibility::Private);

        $this->assertFalse($this->service->canAccess(null, 'FileHosting'));
        $this->assertFalse($this->service->canAccess($this->user('User'), 'FileHosting'));
    }

    public function test_private_domain_allows_root_admin_and_beta_tester(): void
    {
        $this->service->setVisibility('FileHosting', DomainVisibility::Private);

        $this->assertTrue($this->service->canAccess($this->user('Root', 'root@example.com'), 'FileHosting'));
        $this->assertTrue($this->service->canAccess($this->user('Admin', 'admin@example.com'), 'FileHosting'));

        $beta = $this->user('User', 'beta@example.com');
        $beta->assignRole(ProductNames::betaRole('FileHosting'));
        $this->assertTrue($this->service->canAccess($beta->fresh(), 'FileHosting'));
    }

    private function user(string $role, string $email = 'user@example.com'): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole($role);

        return $user;
    }
}
