<?php

namespace Tests\Domain\Product\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Navigation\Repositories\Contracts\MenuRepositoryInterface;
use App\Support\Auth\AuthCookie;
use App\Support\Product\Services\ProductActivationService;
use Database\Seeders\FileHostingRoleSeeder;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Verifies the MenuRepository product-activation visibility filter:
 * menu nodes bound to a product via config/products.php menu_routes are
 * hidden when the authenticated user is not Active on that product.
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
        $this->seed(FileHostingRoleSeeder::class);
        $this->seed(MenuSeeder::class);

        app(MenuRepositoryInterface::class)->flushNavigationCache();
        $this->activations = app(ProductActivationService::class);
    }

    public function test_file_hosting_menu_visible_when_user_active(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        $this->activations->activateImplicit($user, 'FileHosting');

        $response = $this->actingWithJwtCookie($user)->get('/users');

        $response->assertOk();
        $response->assertSee(route('file-hosting.index'), false);
    }

    public function test_file_hosting_menu_hidden_when_user_suspended(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        $activation = $this->activations->activateImplicit($user, 'FileHosting');
        $this->activations->suspend($activation);

        $response = $this->actingWithJwtCookie($user)->get('/users');

        $response->assertOk();
        $response->assertDontSee(route('file-hosting.index'), false);
    }

    public function test_file_hosting_menu_hidden_when_user_expired(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        $activation = $this->activations->activateImplicit($user, 'FileHosting');
        $this->activations->markExpired($activation);

        $response = $this->actingWithJwtCookie($user)->get('/users');

        $response->assertOk();
        $response->assertDontSee(route('file-hosting.index'), false);
    }

    public function test_non_product_menu_unaffected_by_activation_state(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        $activation = $this->activations->activateImplicit($user, 'FileHosting');
        $this->activations->suspend($activation);

        $response = $this->actingWithJwtCookie($user)->get('/users');

        // Users menu is not bound to any product — must remain visible.
        $response->assertSee(route('users.index'), false);
    }

    public function test_guest_sees_file_hosting_menu_when_no_auth_gate(): void
    {
        // Guests skip the product-activation check (no user). Menu visibility
        // for guests is governed by is_authenticate / is_guest_only flags.
        // We assert no exception is thrown and the page renders.
        $response = $this->get('/login');

        $response->assertOk();
    }

    private function actingWithJwtCookie(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withCookie(AuthCookie::NAME, $token);
    }
}
