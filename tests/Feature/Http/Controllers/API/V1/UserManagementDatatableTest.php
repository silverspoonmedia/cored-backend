<?php

namespace Tests\Feature\Http\Controllers\API\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class UserManagementDatatableTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_datatable_mode_includes_rbac_filtered_actions(): void
    {
        $admin = $this->userWithRole('Admin');
        $moderator = User::factory()->create(['email' => 'mod@example.com']);
        $moderator->assignRole('Moderator');
        $peerAdmin = User::factory()->create(['email' => 'peer@example.com']);
        $peerAdmin->assignRole('Admin');

        $response = $this->actingAsJwt($admin)
            ->getJson('/api/v1/users?display_mode=datatable&draw=1&start=0&length=50');

        $response->assertOk()
            ->assertJsonPath('recordsTotal', 1);

        $row = collect($response->json('data'))->firstWhere('email', 'mod@example.com');
        $this->assertNotNull($row);
        $this->assertContains('show', collect($row['actions'])->pluck('key'));
        $this->assertContains('edit', collect($row['actions'])->pluck('key'));
        $this->assertContains('delete', collect($row['actions'])->pluck('key'));

        $this->assertNull(collect($response->json('data'))->firstWhere('email', 'peer@example.com'));
    }

    public function test_query_mode_remains_default_envelope(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonMissingPath('draw');
    }

    /**
     * @return array{Authorization: string}
     */
    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
