<?php

namespace Tests\Feature\Http\Controllers\API\V1;

use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class LookupTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_users_lookup_requires_authentication(): void
    {
        $this->getJson('/api/v1/lookups/users')->assertUnauthorized();
    }

    public function test_users_lookup_paginates_and_searches(): void
    {
        $admin = $this->userWithRole('Admin');
        User::factory()->create(['name' => 'Alice Alpha', 'email' => 'alice@example.com'])->assignRole('User');
        User::factory()->create(['name' => 'Bob Beta', 'email' => 'bob@example.com'])->assignRole('User');
        User::factory()->create(['name' => 'Zara Zulu', 'email' => 'zara@example.com'])->assignRole('User');

        $pageOne = $this->actingAsJwt($admin)
            ->getJson('/api/v1/lookups/users?per_page=2&page=1')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.pagination.more', true);

        $this->assertCount(2, $pageOne->json('data.results'));

        $search = $this->actingAsJwt($admin)
            ->getJson('/api/v1/lookups/users?q=alice')
            ->assertOk();

        $texts = collect($search->json('data.results'))->pluck('text');
        $this->assertTrue($texts->contains(fn (string $text) => str_contains($text, 'Alice Alpha')));
        $this->assertFalse($texts->contains(fn (string $text) => str_contains($text, 'Bob Beta')));
    }

    public function test_users_lookup_defaults_to_ten_per_page(): void
    {
        $admin = $this->userWithRole('Admin');

        for ($i = 1; $i <= 11; $i++) {
            User::factory()->create([
                'name' => "Lookup User {$i}",
                'email' => "lookup-user-{$i}@example.com",
            ])->assignRole('User');
        }

        $response = $this->actingAsJwt($admin)
            ->getJson('/api/v1/lookups/users?page=1')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.pagination.more', true);

        $this->assertCount(10, $response->json('data.results'));
    }

    public function test_users_lookup_hydrates_ids(): void
    {
        $admin = $this->userWithRole('Admin');
        $target = User::factory()->create(['name' => 'Hydrate Me', 'email' => 'hydrate@example.com']);
        $target->assignRole('User');

        $response = $this->actingAsJwt($admin)
            ->getJson('/api/v1/lookups/users?'.http_build_query(['ids' => [$target->id]]))
            ->assertOk()
            ->assertJsonPath('data.pagination.more', false);

        $this->assertSame($target->id, $response->json('data.results.0.id'));
        $this->assertStringContainsString('Hydrate Me', (string) $response->json('data.results.0.text'));
    }

    public function test_users_lookup_respects_role_hierarchy(): void
    {
        $admin = $this->userWithRole('Admin');
        $root = User::factory()->create(['email' => 'root-hidden@example.com']);
        $root->assignRole('Root');

        $response = $this->actingAsJwt($admin)
            ->getJson('/api/v1/lookups/users?q=root-hidden')
            ->assertOk();

        $this->assertCount(0, $response->json('data.results'));
    }

    public function test_menus_lookup_requires_view_menu_permission(): void
    {
        $this->actingAsJwt($this->userWithRole('User'))
            ->getJson('/api/v1/lookups/menus')
            ->assertForbidden();

        $this->actingAsJwt($this->userWithRole('Admin'))
            ->getJson('/api/v1/lookups/menus')
            ->assertOk();
    }

    public function test_menus_lookup_searches_and_excludes_current_menu(): void
    {
        $admin = $this->userWithRole('Admin');
        $current = $this->makeMenu(['name' => ['en' => 'Current Menu'], 'type' => 'c']);
        $sibling = $this->makeMenu(['name' => ['en' => 'Sibling Menu'], 'type' => 'c']);
        $otherLayout = $this->makeMenu(['name' => ['en' => 'Other Layout'], 'type' => 'c', 'layout' => 'navbar']);

        $response = $this->actingAsJwt($admin)
            ->getJson('/api/v1/lookups/menus?'.http_build_query([
                'layout' => 'sidebar',
                'exclude' => $current->id,
                'q' => 'Sibling',
            ]))
            ->assertOk();

        $ids = collect($response->json('data.results'))->pluck('id');
        $this->assertTrue($ids->contains($sibling->id));
        $this->assertFalse($ids->contains($current->id));
        $this->assertFalse($ids->contains($otherLayout->id));
    }

    public function test_roles_lookup_returns_api_guard_roles(): void
    {
        $admin = $this->userWithRole('Admin');

        $response = $this->actingAsJwt($admin)
            ->getJson('/api/v1/lookups/roles')
            ->assertOk();

        $names = collect($response->json('data.results'))->pluck('text');
        $this->assertTrue($names->contains('Admin'));
        $this->assertTrue($names->contains('User'));
    }

    public function test_roles_lookup_hydrates_ids(): void
    {
        $admin = $this->userWithRole('Admin');
        $roleId = Role::query()->where('name', 'Moderator')->where('guard_name', 'api')->value('id');

        $response = $this->actingAsJwt($admin)
            ->getJson('/api/v1/lookups/roles?'.http_build_query(['ids' => [$roleId]]))
            ->assertOk();

        $this->assertSame('Moderator', $response->json('data.results.0.text'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeMenu(array $overrides = []): Menu
    {
        return Menu::query()->create(array_merge([
            'layout' => 'sidebar',
            'name' => ['en' => 'Menu Item'],
            'description' => ['en' => ''],
            'icon' => 'bi bi-circle',
            'route' => null,
            'type' => 'c',
            'order' => 1,
            'is_authenticate' => false,
            'is_guest_only' => false,
        ], $overrides));
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
