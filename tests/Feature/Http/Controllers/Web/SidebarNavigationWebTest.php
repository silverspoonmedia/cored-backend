<?php

namespace Tests\Feature\Http\Controllers\Web;

use App\Models\User;
use App\Repositories\Contracts\MenuRepositoryInterface;
use App\Support\Auth\AuthCookie;
use App\Support\Web\WebLayout;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Guards the sidebar and navbar navigation pipelines: NavigationComposer must
 * bind to the hashed anonymous-component view names so layout-scoped trees
 * reach the Blade shell on every authenticated page.
 */
class SidebarNavigationWebTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(MenuSeeder::class);

        app(MenuRepositoryInterface::class)->flushNavigationCache();
    }

    public function test_sidebar_composer_view_is_registered_for_active_layout(): void
    {
        $this->assertTrue(
            view()->exists(WebLayout::sidebarComposerView()),
            'NavigationComposer cannot run when the sidebar anonymous-component view is missing.',
        );
    }

    public function test_navbar_composer_view_is_registered_for_active_layout(): void
    {
        $this->assertTrue(
            view()->exists(WebLayout::navbarComposerView()),
            'NavigationComposer cannot run when the navbar anonymous-component view is missing.',
        );
    }

    public function test_authenticated_shell_renders_seeded_navigation_in_sidebar(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingWithJwtCookie($admin)->get('/users');

        $response->assertOk();
        $response->assertSee('id="sidebar-menu-root"', false);
        $response->assertSee('MENU', false);
        $response->assertSee('User management', false);
        $response->assertSee(route('users.index'), false);
    }

    public function test_authenticated_shell_renders_navbar_layout_menu(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingWithJwtCookie($admin)->get('/users');

        $response->assertOk();
        $response->assertSee(route('dashboard'), false);
        $response->assertSee('Home', false);
    }

    private function actingWithJwtCookie(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withCookie(AuthCookie::NAME, $token);
    }
}
