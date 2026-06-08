<?php

namespace Tests\Feature\Http\Controllers\API\V1;

use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\MenuRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class NavigationMenuTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_guest_navigation_tree_excludes_authenticate_only(): void
    {
        $this->makeMenu(['name' => 'PublicItem', 'type' => 'c']);
        $this->makeMenu(['name' => 'MembersItem', 'type' => 'c', 'is_authenticate' => true]);

        $response = $this->getJson('/api/v1/navigation/menus')->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('PublicItem'));
        $this->assertFalse($names->contains('MembersItem'));
    }

    public function test_authenticated_navigation_tree_includes_authenticate_only(): void
    {
        $this->makeMenu(['name' => 'MembersItem', 'type' => 'c', 'is_authenticate' => true]);
        $user = $this->userWithRole('User');

        $response = $this->actingAsJwt($user)
            ->getJson('/api/v1/navigation/menus')
            ->assertOk();

        $this->assertTrue(collect($response->json('data'))->pluck('name')->contains('MembersItem'));
    }

    public function test_list_requires_management_role(): void
    {
        $this->getJson('/api/v1/navigation/menus/list')->assertUnauthorized();

        $this->actingAsJwt($this->userWithRole('User'))
            ->getJson('/api/v1/navigation/menus/list')
            ->assertForbidden();

        $this->actingAsJwt($this->userWithRole('Admin'))
            ->getJson('/api/v1/navigation/menus/list')
            ->assertOk();
    }

    public function test_admin_can_create_menu_with_role_assignment(): void
    {
        $admin = $this->userWithRole('Admin');

        $response = $this->actingAsJwt($admin)
            ->postJson('/api/v1/navigation/menus', [
                'name' => 'Reports',
                'type' => 'h',
                'icon' => 'chart',
                'authenticate' => true,
                'roles' => ['Admin'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Reports')
            ->assertJsonPath('data.is_authenticate', true);

        $menuId = $response->json('data.id');
        $adminRoleId = Role::query()->where('name', 'Admin')->where('guard_name', 'api')->value('id');

        $this->assertDatabaseHas('menus', ['id' => $menuId, 'name' => 'Reports']);
        $this->assertDatabaseHas('menu_roles', ['menu_id' => $menuId, 'role_id' => $adminRoleId]);
        $this->assertSame([$adminRoleId], $response->json('data.role_ids'));
    }

    public function test_store_rejects_invalid_type(): void
    {
        $this->actingAsJwt($this->userWithRole('Admin'))
            ->postJson('/api/v1/navigation/menus', ['name' => 'Bad', 'type' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_store_rejects_unregistered_route_name(): void
    {
        $this->actingAsJwt($this->userWithRole('Admin'))
            ->postJson('/api/v1/navigation/menus', [
                'name' => 'Bad Route',
                'type' => 'h',
                'route' => 'this.route.does.not.exist',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['route']);
    }

    public function test_admin_can_update_and_reorder_menu(): void
    {
        $admin = $this->userWithRole('Admin');
        $a = $this->makeMenu(['name' => 'A', 'type' => 'p']);
        $b = $this->makeMenu(['name' => 'B', 'type' => 'p']);

        $this->actingAsJwt($admin)
            ->patchJson('/api/v1/navigation/menus/'.$b->id, [
                'name' => 'B renamed',
                'position' => 'before',
                'reference_id' => $a->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'B renamed')
            ->assertJsonPath('data.order', 1);

        $this->assertSame(2, $a->fresh()->order);
    }

    public function test_update_with_cross_group_reference_returns_422(): void
    {
        $admin = $this->userWithRole('Admin');
        $parent = $this->makeMenu(['name' => 'Parent', 'type' => 'p']);
        $child = $this->makeMenu(['name' => 'Child', 'type' => 'c']);

        $this->actingAsJwt($admin)
            ->patchJson('/api/v1/navigation/menus/'.$child->id, [
                'position' => 'before',
                'reference_id' => $parent->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_admin_can_delete_menu(): void
    {
        $admin = $this->userWithRole('Admin');
        $menu = $this->makeMenu(['name' => 'Temp', 'type' => 'c']);

        $this->actingAsJwt($admin)
            ->deleteJson('/api/v1/navigation/menus/'.$menu->id)
            ->assertOk();

        $this->assertSoftDeleted('menus', ['id' => $menu->id]);
    }

    public function test_delete_blocks_non_empty_parent_without_cascade(): void
    {
        $admin = $this->userWithRole('Admin');
        $header = $this->repo()->createMenu(['name' => 'H', 'type' => 'h'], 'append', null);
        $this->repo()->createMenu(['name' => 'P', 'type' => 'p', 'parent_id' => $header->id], 'append', null);

        $this->actingAsJwt($admin)
            ->deleteJson('/api/v1/navigation/menus/'.$header->id)
            ->assertStatus(409)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('menus', ['id' => $header->id, 'deleted_at' => null]);
    }

    public function test_delete_with_cascade_soft_deletes_subtree(): void
    {
        $admin = $this->userWithRole('Admin');
        $header = $this->repo()->createMenu(['name' => 'H', 'type' => 'h'], 'append', null);
        $parent = $this->repo()->createMenu(['name' => 'P', 'type' => 'p', 'parent_id' => $header->id], 'append', null);
        $child = $this->repo()->createMenu(['name' => 'C', 'type' => 'c', 'parent_id' => $parent->id], 'append', null);

        $this->actingAsJwt($admin)
            ->deleteJson('/api/v1/navigation/menus/'.$header->id.'?cascade=1')
            ->assertOk();

        $this->assertSoftDeleted('menus', ['id' => $header->id]);
        $this->assertSoftDeleted('menus', ['id' => $parent->id]);
        $this->assertSoftDeleted('menus', ['id' => $child->id]);
    }

    public function test_store_rejects_parent_that_is_soft_deleted(): void
    {
        $admin = $this->userWithRole('Admin');
        $header = $this->repo()->createMenu(['name' => 'H', 'type' => 'h'], 'append', null);
        $this->repo()->deleteMenu($header);

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/navigation/menus', [
                'name' => 'Child of trashed',
                'type' => 'p',
                'parent' => $header->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent']);
    }

    public function test_store_rejects_guest_only_with_authenticate(): void
    {
        $this->actingAsJwt($this->userWithRole('Admin'))
            ->postJson('/api/v1/navigation/menus', [
                'name' => 'Impossible',
                'type' => 'h',
                'guest_only' => true,
                'authenticate' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['guest_only']);
    }

    public function test_store_rejects_guest_only_with_roles(): void
    {
        $this->actingAsJwt($this->userWithRole('Admin'))
            ->postJson('/api/v1/navigation/menus', [
                'name' => 'Impossible',
                'type' => 'h',
                'guest_only' => true,
                'roles' => ['Admin'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['guest_only']);
    }

    public function test_store_rejects_child_without_valid_parent(): void
    {
        $this->actingAsJwt($this->userWithRole('Admin'))
            ->postJson('/api/v1/navigation/menus', ['name' => 'Orphan', 'type' => 'c'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent']);
    }

    public function test_store_rejects_header_with_parent(): void
    {
        $header = $this->repo()->createMenu(['name' => 'Root H', 'type' => 'h'], 'append', null);

        $this->actingAsJwt($this->userWithRole('Admin'))
            ->postJson('/api/v1/navigation/menus', [
                'name' => 'Nested Header',
                'type' => 'h',
                'parent' => $header->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent']);
    }

    public function test_update_rejects_reparenting_under_descendant(): void
    {
        $admin = $this->userWithRole('Admin');
        $header = $this->repo()->createMenu(['name' => 'H', 'type' => 'h'], 'append', null);
        $parent = $this->repo()->createMenu(['name' => 'P', 'type' => 'p', 'parent_id' => $header->id], 'append', null);

        // Attempt to make the header a child of its own descendant.
        $this->actingAsJwt($admin)
            ->patchJson('/api/v1/navigation/menus/'.$header->id, ['parent' => $parent->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent']);
    }

    public function test_show_returns_not_found_for_missing_menu(): void
    {
        $this->actingAsJwt($this->userWithRole('Admin'))
            ->getJson('/api/v1/navigation/menus/019e8766-857b-7259-a580-a21e1ddfa27e')
            ->assertNotFound();
    }

    /**
     * Create a menu through the repository so `order` is assigned the same way
     * the application assigns it (contiguous within the sibling group).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function makeMenu(array $attributes): Menu
    {
        return $this->repo()->createMenu(
            array_merge(['name' => 'Menu', 'type' => 'c'], $attributes),
            'append',
            null,
        );
    }

    private function repo(): MenuRepositoryInterface
    {
        return app(MenuRepositoryInterface::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
