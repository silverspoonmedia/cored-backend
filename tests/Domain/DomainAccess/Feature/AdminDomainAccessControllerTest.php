<?php

namespace Tests\Domain\DomainAccess\Feature;

use App\Domain\Identity\Models\User;
use Database\Seeders\DomainAccessSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDomainAccessControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(DomainAccessSeeder::class);
    }

    public function test_admin_can_list_product_domains(): void
    {
        $this->actingAsJwt($this->user('Admin'))
            ->getJson('/api/v1/admin/domain-access')
            ->assertOk()
            ->assertJsonFragment(['key' => 'Archivd'])
            ->assertJsonFragment(['visibility' => 'public']);
    }

    public function test_regular_user_cannot_list(): void
    {
        $this->actingAsJwt($this->user('User'))
            ->getJson('/api/v1/admin/domain-access')
            ->assertForbidden();
    }

    public function test_admin_can_set_visibility(): void
    {
        $this->actingAsJwt($this->user('Admin'))
            ->putJson('/api/v1/admin/domain-access/Archivd', ['visibility' => 'private'])
            ->assertOk()
            ->assertJsonFragment(['visibility' => 'private']);

        $this->assertDatabaseHas('product_domain_visibilities', [
            'product_key' => 'Archivd',
            'visibility' => 'private',
        ]);
    }

    public function test_update_rejects_invalid_visibility(): void
    {
        $this->actingAsJwt($this->user('Admin'))
            ->putJson('/api/v1/admin/domain-access/Archivd', ['visibility' => 'bogus'])
            ->assertStatus(422);
    }

    public function test_update_rejects_unknown_product(): void
    {
        $this->actingAsJwt($this->user('Admin'))
            ->putJson('/api/v1/admin/domain-access/Identity', ['visibility' => 'private'])
            ->assertNotFound();
    }

    public function test_regular_user_cannot_update(): void
    {
        $this->actingAsJwt($this->user('User'))
            ->putJson('/api/v1/admin/domain-access/Archivd', ['visibility' => 'private'])
            ->assertForbidden();
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
