<?php

namespace Tests\Domain\Product\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Navigation\Repositories\Contracts\MenuRepositoryInterface;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\ArchivdRoleSeeder;
use App\Domain\Navigation\Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Verifies the MenuRepository product-activation visibility filter via the
 * navigation API: menu nodes bound to a product via config/products.php
 * menu_routes are hidden when the authenticated user is not Active on that
 * product. (The Blade app is gone; the tree is now served as JSON.)
 */
class MenuVisibilityTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private ProductActivationService $activations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(ArchivdRoleSeeder::class);
        $this->seed(MenuSeeder::class);

        app(MenuRepositoryInterface::class)->flushNavigationCache();
        $this->activations = app(ProductActivationService::class);
    }

    public function test_archivd_menu_visible_when_user_active(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        $this->activations->activateImplicit($user, 'Archivd');

        $names = $this->sidebarMenuNames($user);

        $this->assertContains('Archivd', $names);
    }

    public function test_archivd_menu_hidden_when_user_suspended(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        $activation = $this->activations->activateImplicit($user, 'Archivd');
        $this->activations->suspend($activation);

        $names = $this->sidebarMenuNames($user);

        $this->assertNotContains('Archivd', $names);
    }

    public function test_archivd_menu_hidden_when_user_expired(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        $activation = $this->activations->activateImplicit($user, 'Archivd');
        $this->activations->markExpired($activation);

        $names = $this->sidebarMenuNames($user);

        $this->assertNotContains('Archivd', $names);
    }

    public function test_non_product_menu_unaffected_by_activation_state(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        $activation = $this->activations->activateImplicit($user, 'Archivd');
        $this->activations->suspend($activation);

        $names = $this->sidebarMenuNames($user);

        // Dashboard is not bound to any product - must remain visible.
        $this->assertContains('Dashboard', $names);
    }

    /**
     * Fetch the presented sidebar tree and flatten every node name (parents +
     * children) so product-bound parents and their leaves are both observed.
     *
     * @return array<int, string>
     */
    private function sidebarMenuNames(User $user): array
    {
        $tree = $this->actingAsJwt($user)
            ->getJson('/api/v1/navigation/menus?layout=sidebar')
            ->assertOk()
            ->json('data');

        $names = [];
        $walk = function (array $nodes) use (&$walk, &$names): void {
            foreach ($nodes as $node) {
                if (isset($node['name'])) {
                    $names[] = $node['name'];
                }
                if (! empty($node['children']) && is_array($node['children'])) {
                    $walk($node['children']);
                }
            }
        };
        $walk(is_array($tree) ? $tree : []);

        return $names;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
