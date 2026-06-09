<?php

namespace Tests\Feature\Http\Controllers\API\V1;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class NavigationMenuDatatableTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_list_datatable_mode_returns_actions_for_admin(): void
    {
        $admin = $this->userWithRole('Admin');
        $menu = Menu::factory()->create(['name' => 'DatatableMenu', 'type' => 'h']);

        $response = $this->actingAsJwt($admin)
            ->getJson('/api/v1/navigation/menus/list?display_mode=datatable&draw=1');

        $response->assertOk()
            ->assertJsonPath('recordsTotal', 1);

        $row = collect($response->json('data'))->firstWhere('id', $menu->id);
        $this->assertNotNull($row);
        $keys = collect($row['actions'])->pluck('key');
        $this->assertTrue($keys->contains('show'));
        $this->assertTrue($keys->contains('edit'));
        $this->assertTrue($keys->contains('delete'));
    }

    public function test_list_datatable_mode_limits_actions_for_view_only_role(): void
    {
        $viewer = $this->userWithRole('Management');
        Menu::factory()->create(['name' => 'ViewOnlyMenu', 'type' => 'h']);

        $response = $this->actingAsJwt($viewer)
            ->getJson('/api/v1/navigation/menus/list?display_mode=datatable&draw=1');

        $response->assertOk();
        $keys = collect($response->json('data.0.actions'))->pluck('key');
        $this->assertSame(['show'], $keys->values()->all());
    }

    public function test_trashed_datatable_includes_restore_action(): void
    {
        $admin = $this->userWithRole('Admin');
        $menu = Menu::factory()->create(['name' => 'TrashedMenu', 'type' => 'c']);
        $menu->delete();

        $response = $this->actingAsJwt($admin)
            ->getJson('/api/v1/navigation/menus/trashed?display_mode=datatable&draw=1');

        $response->assertOk();
        $keys = collect($response->json('data.0.actions'))->pluck('key');
        $this->assertTrue($keys->contains('restore'));
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
