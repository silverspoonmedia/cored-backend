<?php

namespace Tests\Feature\Http\Controllers\Web;

use App\Domain\Identity\Models\User;
use App\Support\Auth\AuthCookie;
use Database\Seeders\VtualRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * HTTP-level smoke tests for the vTual web surface after the UI/UX overhaul.
 *
 * Verifies that every polished Blade view renders (200), the shared nav +
 * confirm modal partials are present, and mutation forms route to the AJAX
 * component (data-ajax-form) rather than relying on native confirm().
 */
class VtualBladeWebTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private string $creatorId;
    private string $platformId;
    private string $claimId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(VtualRoleSeeder::class);
        $this->creatorId = (string) \Illuminate\Support\Str::uuid();
        $this->platformId = (string) \Illuminate\Support\Str::uuid();
        $this->claimId = (string) \Illuminate\Support\Str::uuid();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function actingWithJwtCookie(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withCookie(AuthCookie::NAME, $token);
    }

    private function fakeVtualApi(): void
    {
        Http::fake([
            '*/api/v1/auth/me' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 'test',
                    'name' => 'Test',
                    'email' => 'test@example.com',
                    'roles' => [],
                    'permissions' => [],
                    'email_verified_at' => now()->toIso8601String(),
                    'two_factor_enabled' => false,
                ],
            ], 200),
            '*/api/v1/vtual/creators*' => Http::response([
                'status' => 'success',
                'data' => [
                    [
                        'id' => $this->creatorId,
                        'display_name' => 'Test Vtuber',
                        'slug' => 'test-vtuber',
                        'bio' => 'Test bio',
                        'avatar_url' => null,
                        'status' => 'active',
                        'my_role' => 'owner',
                        'channels_count' => 1,
                        'external_links' => [],
                    ],
                ],
            ], 200),
            '*/api/v1/vtual/platforms*' => Http::response([
                'status' => 'success',
                'data' => [
                    ['id' => $this->platformId, 'key' => 'youtube', 'name' => 'YouTube', 'enabled' => true],
                ],
            ], 200),
            '*/api/v1/vtual/managements*' => Http::response(['status' => 'success', 'data' => []], 200),
            '*/api/v1/vtual/claims*' => Http::response(['status' => 'success', 'data' => []], 200),
            '*/api/v1/vtual/channels*' => Http::response(['status' => 'success', 'data' => []], 200),
            '*/api/v1/vtual/channel-claims*' => Http::response(['status' => 'success', 'data' => []], 200),
            '*/api/v1/vtual/links*' => Http::response(['status' => 'success', 'data' => []], 200),
            '*/api/v1/vtual/admin/*' => Http::response(['status' => 'success', 'data' => []], 200),
        ]);
    }

    public function test_index_page_renders_hub_cards_and_nav(): void
    {
        $user = $this->userWithRole('User');

        $this->fakeVtualApi();

        $html = $this->actingWithJwtCookie($user)
            ->get(route('vtual.index'))
            ->assertOk()
            ->assertSee(__('web/vtual.title'), false)
            ->assertSee('aria-current="page"', false)
            ->getContent();

        $this->assertStringContainsString('nav-pills', $html);
        $this->assertStringContainsString('data-ajax-form', $html);
    }

    public function test_profiles_index_renders_table_with_caption(): void
    {
        $user = $this->userWithRole('User');

        $this->fakeVtualApi();

        $html = $this->actingWithJwtCookie($user)
            ->get(route('vtual.profiles.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<caption', $html);
        $this->assertStringContainsString(__('web/vtual.table.caption_profiles'), $html);
    }

    public function test_profiles_show_renders_partials_and_confirm_modal(): void
    {
        $user = $this->userWithRole('User');

        $this->fakeVtualApi();

        $html = $this->actingWithJwtCookie($user)
            ->get(route('vtual.profiles.show', $this->creatorId))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-ajax-form', $html, 'profile show must ship AJAX forms.');
        $this->assertStringContainsString('id="vtualConfirmModal"', $html, 'confirm modal partial must be included.');
        $this->assertStringContainsString('data-confirm-body', $html, 'confirm triggers must carry data-confirm-body.');
        $this->assertStringContainsString('confirm-modal', $html, 'confirm-modal.js Vite entry must be registered.');
    }

    public function test_claims_index_renders_claimable_and_mine_tables(): void
    {
        $user = $this->userWithRole('User');

        $this->fakeVtualApi();

        $html = $this->actingWithJwtCookie($user)
            ->get(route('vtual.claims.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('web/vtual.claims.claimable'), $html);
        $this->assertStringContainsString(__('web/vtual.claims.mine'), $html);
        $this->assertStringContainsString('data-ajax-form', $html);
    }

    public function test_claims_show_renders_confirm_modal_for_cancel(): void
    {
        $user = $this->userWithRole('User');

        Http::fake([
            '*/api/v1/auth/me' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 'test',
                    'name' => 'Test',
                    'email' => 'test@example.com',
                    'roles' => [],
                    'permissions' => [],
                    'email_verified_at' => now()->toIso8601String(),
                    'two_factor_enabled' => false,
                ],
            ], 200),
            '*/api/v1/vtual/claims/*' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $this->claimId,
                    'status' => 'pending',
                    'token' => 'tok123',
                    'attempt_count' => 0,
                    'expires_at' => '2026-12-31T23:59:59Z',
                    'user' => ['name' => 'Claimant', 'email' => 'c@example.com'],
                    'creator' => ['display_name' => 'Test Vtuber'],
                    'channel' => ['platform' => ['name' => 'YouTube'], 'handle' => '@test'],
                ],
            ], 200),
        ]);

        $html = $this->actingWithJwtCookie($user)
            ->get(route('vtual.claims.show', $this->claimId))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="vtualConfirmModal"', $html);
        $this->assertStringContainsString(__('web/vtual.confirm.cancel_claim_title'), $html);
    }

    public function test_staff_registry_requires_manage_any_permission(): void
    {
        $user = $this->userWithRole('User');

        $this->fakeVtualApi();

        $this->actingWithJwtCookie($user)
            ->get(route('vtual.claims.staff'))
            ->assertRedirect(route('vtual.index'));
    }

    public function test_admin_credentials_index_requires_credential_permission(): void
    {
        $root = $this->userWithRole('Root');

        $this->fakeVtualApi();

        $this->actingWithJwtCookie($root)
            ->get(route('vtual.admin.credentials.index'))
            ->assertOk()
            ->assertSee(__('web/vtual.credentials.title'), false);
    }

    public function test_platform_api_probe_renders_ajax_form(): void
    {
        $root = $this->userWithRole('Root');

        $this->actingWithJwtCookie($root)
            ->get(route('vtual.admin.platform-api-probe'))
            ->assertOk()
            ->assertSee('data-ajax-form', false);
    }

    public function test_no_view_uses_native_confirm_dialog(): void
    {
        $views = glob(resource_path('views/adminlte4/vtual/**/*.blade.php'));

        $this->assertNotEmpty($views, 'vTual views must exist.');

        foreach ($views as $path) {
            $blade = (string) file_get_contents($path);

            $this->assertStringNotContainsString(
                'confirm(',
                $blade,
                basename($path).' must not use native confirm() — use the shared Bootstrap modal.',
            );
        }
    }

    public function test_all_tables_expose_caption_or_aria_label(): void
    {
        $views = glob(resource_path('views/adminlte4/vtual/**/*.blade.php'));

        foreach ($views as $path) {
            $blade = (string) file_get_contents($path);

            if (! str_contains($blade, '<table')) {
                continue;
            }

            $this->assertTrue(
                str_contains($blade, '<caption') || str_contains($blade, 'aria-label="'),
                basename($path).' tables must expose <caption> or aria-label.',
            );
        }
    }
}
