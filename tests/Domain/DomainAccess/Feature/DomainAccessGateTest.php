<?php

namespace Tests\Domain\DomainAccess\Feature;

use App\Domain\Identity\Models\User;
use App\Support\DomainAccess\DomainAccessService;
use App\Support\DomainAccess\Enums\DomainVisibility;
use App\Support\DomainAccess\ProductNames;
use Database\Seeders\DomainAccessSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DomainAccessGateTest extends TestCase
{
    use RefreshDatabase;

    private DomainAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(DomainAccessSeeder::class);
        $this->service = app(DomainAccessService::class);

        // Isolated probe routes guarded only by the gate under test.
        Route::middleware(['api', 'auth:api', 'domain.access:Archivd'])
            ->get('/api/v1/_probe/domain-access', fn () => response()->json(['ok' => true]));

        Route::middleware(['web', 'domain.access:Archivd'])
            ->get('/_probe/domain-access', fn () => response('ok'));
    }

    public function test_public_domain_passes_for_plain_user_api(): void
    {
        $this->service->setVisibility('Archivd', DomainVisibility::Public);

        $this->actingAsJwt($this->user('User'))
            ->getJson('/api/v1/_probe/domain-access')
            ->assertOk();
    }

    public function test_private_domain_blocks_plain_user_api(): void
    {
        $this->service->setVisibility('Archivd', DomainVisibility::Private);

        $this->actingAsJwt($this->user('User'))
            ->getJson('/api/v1/_probe/domain-access')
            ->assertForbidden();
    }

    public function test_private_domain_allows_root_admin_and_beta_api(): void
    {
        $this->service->setVisibility('Archivd', DomainVisibility::Private);

        $this->actingAsJwt($this->user('Root', 'root@example.com'))
            ->getJson('/api/v1/_probe/domain-access')
            ->assertOk();

        $this->actingAsJwt($this->user('Admin', 'admin@example.com'))
            ->getJson('/api/v1/_probe/domain-access')
            ->assertOk();

        $beta = $this->user('User', 'beta@example.com');
        $beta->assignRole(ProductNames::betaRole('Archivd'));

        $this->actingAsJwt($beta->fresh())
            ->getJson('/api/v1/_probe/domain-access')
            ->assertOk();
    }

    public function test_private_domain_blocks_plain_user_web(): void
    {
        $this->service->setVisibility('Archivd', DomainVisibility::Private);

        $this->actingAs($this->user('User'))
            ->get('/_probe/domain-access')
            ->assertForbidden();
    }

    public function test_public_domain_passes_web(): void
    {
        $this->service->setVisibility('Archivd', DomainVisibility::Public);

        $this->actingAs($this->user('User'))
            ->get('/_probe/domain-access')
            ->assertOk();
    }

    private function user(string $role, string $email = 'user@example.com'): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole($role);

        return $user;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
