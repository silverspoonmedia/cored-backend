<?php

namespace Tests\Unit\Support\ListActions;

use App\Domain\Identity\Models\User;
use App\Support\ListActions\Builders\UserQueryRowActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class UserQueryRowActionsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private UserQueryRowActions $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->builder = new UserQueryRowActions;
    }

    public function test_admin_gets_edit_and_delete_for_another_user(): void
    {
        $admin = $this->userWithRole('Admin');
        $moderator = $this->userWithRole('Moderator');

        $keys = collect($this->builder->resolve($moderator, $admin))->pluck('key')->all();

        $this->assertSame(['edit', 'delete'], $keys);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = $this->userWithRole('Admin');

        $keys = collect($this->builder->resolve($admin, $admin))->pluck('key')->all();

        $this->assertSame(['edit'], $keys);
    }

    public function test_moderator_without_delete_user_only_gets_edit(): void
    {
        $moderator = $this->userWithRole('Moderator');
        $user = $this->userWithRole('User');

        $keys = collect($this->builder->resolve($user, $moderator))->pluck('key')->all();

        $this->assertSame(['edit'], $keys);
    }

    public function test_edit_is_spa_nav_not_api_patch(): void
    {
        $admin = $this->userWithRole('Admin');
        $moderator = $this->userWithRole('Moderator');

        $edit = collect($this->builder->resolve($moderator, $admin))->firstWhere('key', 'edit');

        $this->assertNotNull($edit);
        $this->assertSame('users.edit', $edit->route);
        $this->assertSame(['id' => (string) $moderator->id], $edit->params);
        $this->assertNull($edit->method);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
