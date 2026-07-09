<?php

namespace Tests\Feature\Http\Controllers\API\V1;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_admin_lists_peers_and_lower_but_not_higher_authority(): void
    {
        $admin = $this->userWithRole('Admin');
        $moderator = User::factory()->create(['email' => 'mod@example.com']);
        $moderator->assignRole('Moderator');
        $peerAdmin = User::factory()->create(['email' => 'peer@example.com']);
        $peerAdmin->assignRole('Admin');
        $root = User::factory()->create(['email' => 'root@example.com']);
        $root->assignRole('Root');

        $response = $this->actingAsJwt($admin)->getJson('/api/v1/users');

        $response->assertOk();
        $emails = collect($response->json('data'))->pluck('email');
        $this->assertTrue($emails->contains('mod@example.com'));
        $this->assertTrue($emails->contains('peer@example.com'));
        $this->assertFalse($emails->contains('root@example.com'));
    }

    public function test_admin_can_view_peer_admin_detail(): void
    {
        $admin = $this->userWithRole('Admin');
        $peer = User::factory()->create();
        $peer->assignRole('Admin');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/users/'.$peer->id)
            ->assertOk()
            ->assertJsonPath('data.id', $peer->id);
    }

    public function test_admin_cannot_view_higher_authority_detail(): void
    {
        $admin = $this->userWithRole('Admin');
        $root = User::factory()->create();
        $root->assignRole('Root');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/users/'.$root->id)
            ->assertForbidden()
            ->assertJson([
                'status' => 'error',
                'message' => __('api/users.policy.hierarchy_view'),
            ]);
    }

    public function test_user_can_view_their_own_profile(): void
    {
        $user = $this->userWithRole('User');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/users/'.$user->id)
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_show_returns_not_found_when_user_does_not_exist(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/users/019e8766-857b-7259-a580-a21e1ddfa27e')
            ->assertNotFound()
            ->assertJson([
                'status' => 'error',
                'message' => __('api/users.not_found'),
            ]);
    }

    public function test_admin_can_create_user_with_lower_role(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/users', [
                'name' => 'New Moderator',
                'email' => 'newmod@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'Moderator',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'newmod@example.com')
            ->assertJsonPath('data.roles.0.name', 'Moderator');

        $this->assertDatabaseHas('users', ['email' => 'newmod@example.com']);
    }

    public function test_admin_cannot_assign_admin_role_on_create(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/users', [
                'name' => 'Bad Admin',
                'email' => 'badadmin@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'Admin',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_management_cannot_create_users(): void
    {
        $manager = $this->userWithRole('Management');

        $this->actingAsJwt($manager)
            ->postJson('/api/v1/users', [
                'name' => 'Someone',
                'email' => 'someone@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'User',
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->deleteJson('/api/v1/users/'.$admin->id)
            ->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => __('api/users.cannot_delete_self'),
            ]);
    }

    public function test_soft_delete_removes_user_from_list(): void
    {
        $admin = $this->userWithRole('Admin');
        $target = User::factory()->create();
        $target->assignRole('User');

        $this->actingAsJwt($admin)
            ->deleteJson('/api/v1/users/'.$target->id)
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $target->id]);

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/users')
            ->assertOk();

        $emails = collect($this->getJson('/api/v1/users')->json('data'))->pluck('email');
        $this->assertFalse($emails->contains($target->email));
    }

    public function test_assignable_roles_excludes_actor_level_and_above(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/users/assignable-roles')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Moderator'])
            ->assertJsonFragment(['name' => 'User'])
            ->assertJsonMissing(['name' => 'Admin'])
            ->assertJsonMissing(['name' => 'Root']);
    }

    public function test_moderator_cannot_delete_users(): void
    {
        $moderator = $this->userWithRole('Moderator');
        $target = User::factory()->create();
        $target->assignRole('User');

        $this->actingAsJwt($moderator)
            ->deleteJson('/api/v1/users/'.$target->id)
            ->assertForbidden()
            ->assertJson([
                'status' => 'error',
                'message' => __('api/users.policy.delete'),
            ]);
    }

    public function test_actor_with_multiple_roles_uses_lowest_level(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole(['Admin', 'Management']);

        $target = User::factory()->create(['email' => 'peer2@example.com']);
        $target->assignRole('Moderator');

        $this->actingAsJwt($actor)
            ->getJson('/api/v1/users/'.$target->id)
            ->assertOk()
            ->assertJsonPath('data.id', $target->id);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole($roleName);

        return $user;
    }

    /**
     * @return array{Authorization: string}
     */
    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
