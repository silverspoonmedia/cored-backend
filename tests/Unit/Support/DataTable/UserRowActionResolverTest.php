<?php

namespace Tests\Unit\Support\DataTable;

use App\Domain\Identity\Models\User;
use App\Support\DataTable\Resolvers\UserRowActionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class UserRowActionResolverTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private UserRowActionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->resolver = new UserRowActionResolver;
    }

    public function test_admin_receives_full_actions_for_lower_role_user(): void
    {
        $admin = $this->userWithRole('Admin');
        $moderator = User::factory()->create();
        $moderator->assignRole('Moderator');

        $keys = collect($this->resolver->resolve($moderator, $admin))->pluck('key');

        $this->assertSame(['show', 'edit', 'delete'], $keys->values()->all());
    }

    public function test_peer_admin_only_receives_view_action(): void
    {
        $admin = $this->userWithRole('Admin');
        $peer = User::factory()->create();
        $peer->assignRole('Admin');

        $keys = collect($this->resolver->resolve($peer, $admin))->pluck('key');

        $this->assertSame(['show'], $keys->values()->all());
    }

    public function test_higher_authority_receives_no_actions(): void
    {
        $admin = $this->userWithRole('Admin');
        $root = User::factory()->create();
        $root->assignRole('Root');

        $actions = $this->resolver->resolve($root, $admin);

        $this->assertSame([], $actions);
    }

    public function test_self_only_receives_view_action(): void
    {
        $admin = $this->userWithRole('Admin');

        $keys = collect($this->resolver->resolve($admin, $admin))->pluck('key');

        $this->assertSame(['show'], $keys->values()->all());
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
