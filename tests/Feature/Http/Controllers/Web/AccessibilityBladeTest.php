<?php

namespace Tests\Feature\Http\Controllers\Web;

use App\Domain\Identity\Models\User;
use App\Support\Auth\AuthCookie;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Guards core accessibility (a11y) invariants for the Blade admin:
 *  - skip-link to #main-content (WCAG 2.4.1)
 *  - auth inputs ship visual <label> (WCAG 1.3.1, 4.1.2)
 *  - icon-only buttons expose aria-label / title (WCAG 4.1.2)
 *  - breadcrumb <nav aria-label="breadcrumb"> (WCAG 1.3.1)
 *  - navbar dropdown toggles expose initial aria-expanded (WCAG 4.1.2)
 *  - active nav links expose aria-current="page" (WCAG 1.3.1)
 *  - DataTable tables expose aria-label / <caption> (WCAG 1.3.1)
 *  - status regions expose role="status" + aria-live (WCAG 4.1.3)
 */
class AccessibilityBladeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(MenuSeeder::class);
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

    public function test_login_page_has_skip_link_and_main_content_target(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('href="#main-content"', false)
            ->assertSee('visually-hidden-focusable', false)
            ->assertSee('id="main-content"', false);
    }

    public function test_login_form_renders_visible_labels_for_email_and_password(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<label', false)
            ->assertSee(__('ui.labels.email'), false)
            ->assertSee(__('ui.labels.password'), false);
    }

    public function test_register_form_renders_visible_labels(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee(__('ui.labels.name'), false)
            ->assertSee(__('ui.labels.email'), false);
    }

    public function test_forgot_password_form_renders_visible_email_label(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee(__('ui.labels.email'), false);
    }

    public function test_two_factor_challenge_form_renders_visible_code_label(): void
    {
        $this->withSession(['two_factor_token' => 'fake-token'])
            ->get(route('two-factor.challenge'))
            ->assertOk()
            ->assertSee(__('web/auth.two_factor_code_label'), false);
    }

    public function test_dashboard_renders_skip_link_and_main_content_target(): void
    {
        $admin = $this->userWithRole('Admin');

        Http::fake(['*/api/v1/auth/me' => Http::response([
            'status' => 'success',
            'data' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'roles' => ['Admin'],
                'permissions' => [],
                'email_verified_at' => now()->toIso8601String(),
                'two_factor_enabled' => false,
            ],
        ], 200)]);

        $this->actingWithJwtCookie($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="#main-content"', false)
            ->assertSee('id="main-content"', false);
    }

    public function test_dashboard_breadcrumbs_wrapped_in_nav_with_aria_label(): void
    {
        $admin = $this->userWithRole('Admin');

        Http::fake(['*/api/v1/auth/me' => Http::response([
            'status' => 'success',
            'data' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'roles' => ['Admin'],
                'permissions' => [],
                'email_verified_at' => now()->toIso8601String(),
                'two_factor_enabled' => false,
            ],
        ], 200)]);

        $this->actingWithJwtCookie($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="'.__('ui.labels.breadcrumb').'"', false);
    }

    public function test_navbar_dropdown_toggles_expose_initial_aria_expanded_false(): void
    {
        $admin = $this->userWithRole('Admin');

        Http::fake(['*/api/v1/auth/me' => Http::response([
            'status' => 'success',
            'data' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'roles' => ['Admin'],
                'permissions' => [],
                'email_verified_at' => now()->toIso8601String(),
                'two_factor_enabled' => false,
            ],
        ], 200)]);

        $html = $this->actingWithJwtCookie($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('aria-expanded="false"', $html);
    }

    public function test_users_index_datatable_exposes_caption_and_aria_label(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingWithJwtCookie($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('<caption', false)
            ->assertSee('aria-label="'.__('ui.labels.users_table').'"', false);
    }

    public function test_breadcrumbs_blade_wraps_ol_in_nav_with_aria_label(): void
    {
        $blade = (string) file_get_contents(resource_path('views/adminlte4/partials/breadcrumbs.blade.php'));

        $this->assertStringContainsString('<nav', $blade);
        $this->assertStringContainsString("aria-label=\"{{ __('ui.labels.breadcrumb') }}\"", $blade);
    }

    public function test_payment_status_hero_blade_exposes_status_role_and_aria_live(): void
    {
        $blade = (string) file_get_contents(resource_path('views/adminlte4/components/payments/payment-status-hero.blade.php'));

        $this->assertStringContainsString('role="status"', $blade);
        $this->assertStringContainsString('aria-live="polite"', $blade);
    }

    public function test_nav_bar_tree_blade_renders_aria_current_on_active_link(): void
    {
        $this->assertStringContainsString(
            'aria-current="page"',
            (string) file_get_contents(resource_path('views/adminlte4/components/nav/bar-tree.blade.php')),
        );
    }

    public function test_nav_sidebar_tree_blade_renders_aria_current_on_active_link(): void
    {
        $this->assertStringContainsString(
            'aria-current="page"',
            (string) file_get_contents(resource_path('views/adminlte4/components/nav/tree.blade.php')),
        );
    }

    public function test_bell_js_source_augments_badge_with_role_and_aria_live(): void
    {
        $js = (string) file_get_contents(resource_path('js/notifications/bell.js'));

        $this->assertStringContainsString("badge.setAttribute('role', 'status')", $js);
        $this->assertStringContainsString("badge.setAttribute('aria-live', 'polite')", $js);
        $this->assertStringContainsString("list.setAttribute('aria-live', 'polite')", $js);
        $this->assertStringContainsString('notification-realtime:polling', $js);
        $this->assertStringContainsString('enterPollingMode', $js);
    }

    public function test_bell_js_loads_from_assets_scripts_not_navbar_push(): void
    {
        $assets = (string) file_get_contents(resource_path('views/adminlte4/components/partials/assets-scripts.blade.php'));
        $navbar = (string) file_get_contents(resource_path('views/adminlte4/components/partials/navbar.blade.php'));
        $users = (string) file_get_contents(resource_path('views/adminlte4/users/index.blade.php'));

        $this->assertStringContainsString("@can('view notification')", $assets);
        $this->assertStringContainsString("@vite('resources/js/notifications/bell.js')", $assets);

        $appPos = strpos($assets, "@vite('resources/js/app.js')");
        $bellPos = strpos($assets, "@vite('resources/js/notifications/bell.js')");
        $this->assertNotFalse($appPos);
        $this->assertNotFalse($bellPos);
        $this->assertLessThan($bellPos, $appPos, 'app.js must load before bell.js in assets-scripts.');

        $this->assertStringNotContainsString('notifications/bell.js', $navbar);
        $this->assertStringContainsString('notification-realtime:polling', $users);
    }

    public function test_checkout_js_source_sets_aria_pressed_on_channel_buttons(): void
    {
        $js = (string) file_get_contents(resource_path('js/payments/checkout.js'));

        $this->assertStringContainsString("setAttribute('aria-pressed'", $js);
    }

    public function test_file_hosting_vue_move_modal_list_exposes_role_button_targets(): void
    {
        $vue = (string) file_get_contents(resource_path('js/vue/islands/file-hosting/App.vue'));

        $this->assertStringContainsString('data-fh-drop-folder', $vue);
        $this->assertStringContainsString('draggable="true"', $vue);
        $this->assertStringContainsString('useFhDragMove', $vue);
    }

    public function test_file_hosting_dropzone_vue_exposes_role_button_and_tabindex(): void
    {
        $vue = (string) file_get_contents(resource_path('js/vue/islands/file-hosting/components/FhDropzone.vue'));

        $this->assertStringContainsString('role="button"', $vue);
        $this->assertStringContainsString('tabindex="0"', $vue);
    }

    public function test_empty_state_component_renders_role_status_and_icon(): void
    {
        $blade = (string) file_get_contents(resource_path('views/adminlte4/components/empty-state.blade.php'));

        $this->assertStringContainsString('role="status"', $blade);
        $this->assertStringContainsString('aria-hidden="true"', $blade);
    }

    public function test_checkout_js_does_not_use_window_alert(): void
    {
        $js = (string) file_get_contents(resource_path('js/payments/checkout.js'));

        $this->assertStringNotContainsString('window.alert', $js, 'checkout.js must use toast, not window.alert.');
        $this->assertStringContainsString('showCheckoutToast', $js);
    }

    public function test_assets_head_meta_uses_indigo_theme_color_token(): void
    {
        $blade = (string) file_get_contents(resource_path('views/adminlte4/components/partials/assets-head.blade.php'));

        $this->assertStringNotContainsString('#007bff', $blade, 'theme-color still hardcoded Bootstrap blue.');
        $this->assertStringContainsString('#6366f1', $blade, 'theme-color must use indigo accent token.');
    }

    public function test_profile_password_blade_renders_cancel_button(): void
    {
        $blade = (string) file_get_contents(resource_path('views/adminlte4/profile/password.blade.php'));

        $this->assertStringContainsString("__('ui.actions.cancel')", $blade);
    }

    public function test_profile_two_factor_blade_renders_cancel_button(): void
    {
        $blade = (string) file_get_contents(resource_path('views/adminlte4/profile/two-factor.blade.php'));

        $this->assertStringContainsString("__('ui.actions.cancel')", $blade);
    }

    public function test_error_logs_index_blade_renders_reset_filter_link(): void
    {
        $blade = (string) file_get_contents(resource_path('views/adminlte4/error-logs/index.blade.php'));

        $this->assertStringContainsString("__('ui.actions.reset')", $blade);
    }

    public function test_menus_trashed_blade_does_not_confirm_on_restore(): void
    {
        $blade = (string) file_get_contents(resource_path('views/adminlte4/menus/trashed.blade.php'));

        $this->assertStringNotContainsString('confirm(', $blade, 'restore must not prompt confirm (non-destructive).');
    }

    public function test_navbar_logout_and_mark_all_read_expose_submit_spinner(): void
    {
        $blade = (string) file_get_contents(resource_path('views/adminlte4/components/partials/navbar.blade.php'));

        $this->assertSame(
            2,
            substr_count($blade, 'data-form-submit-spinner'),
            'navbar logout + mark-all-read must each carry a submit spinner.',
        );
    }

    public function test_services_show_blade_renders_single_checkout_form(): void
    {
        $blade = (string) file_get_contents(resource_path('views/adminlte4/services/show.blade.php'));

        $this->assertSame(
            1,
            substr_count($blade, 'route(\'services.checkout\''),
            'services/show must ship a single checkout form, not two duplicated forms.',
        );
    }

    public function test_checkout_panel_body_blade_explains_confirm_paid(): void
    {
        $blade = (string) file_get_contents(resource_path('views/adminlte4/components/payments/checkout-panel-body.blade.php'));

        $this->assertStringContainsString('confirm_paid_help', $blade);
    }

    public function test_payment_channels_index_blade_warns_priority_autosave(): void
    {
        $blade = (string) file_get_contents(resource_path('views/adminlte4/payment-channels/index.blade.php'));

        $this->assertStringContainsString('priority_autosave_hint', $blade);
    }

    public function test_payment_channels_and_domain_access_url_templates_use_named_routes(): void
    {
        $channels = (string) file_get_contents(resource_path('views/adminlte4/payment-channels/index.blade.php'));
        $domainAccess = (string) file_get_contents(resource_path('views/adminlte4/domain-access/index.blade.php'));

        $this->assertStringContainsString(
            "route('payment-channels.update'",
            $channels,
            'payment-channels AJAX template must use named route (not url()).',
        );
        $this->assertStringContainsString(
            "route('payment-channel-routes.update'",
            $channels,
            'payment-channel-routes AJAX template must use named route (not url()).',
        );
        $this->assertStringNotContainsString(
            "url('payment-channels')",
            $channels,
            'payment-channels must not hard-code url() for auth shell paths.',
        );
        $this->assertStringContainsString(
            "route('domain-access.update'",
            $domainAccess,
            'domain-access AJAX template must use named route (not url()).',
        );
        $this->assertStringNotContainsString(
            "url('domain-access')",
            $domainAccess,
            'domain-access must not hard-code url() for auth shell paths.',
        );
    }

    public function test_instruction_media_js_does_not_call_third_party_qr_service(): void
    {
        $js = (string) file_get_contents(resource_path('js/payments/instructionMedia.js'));
        $panel = (string) file_get_contents(resource_path('views/adminlte4/components/payments/checkout-panel.blade.php'));

        $this->assertStringNotContainsString('api.qrserver.com', $js, 'instructionMedia.js must not leak payload to third-party QR service.');
        $this->assertStringContainsString('monolithQrBaseUrl', $js, 'instructionMedia.js must read QR base from window.monolithQrBaseUrl.');
        $this->assertStringNotContainsString("'/apps/qr'", $js, 'instructionMedia.js must not hard-code /apps/qr path.');
        $this->assertStringNotContainsString('"/apps/qr"', $js, 'instructionMedia.js must not hard-code /apps/qr path.');
        $this->assertStringContainsString(
            "route('qr.show')",
            $panel,
            'checkout panel must inject QR base via route(qr.show).',
        );
        $this->assertStringContainsString('monolithQrBaseUrl', $panel);
    }

    public function test_activity_log_breadcrumb_parent_is_clickable(): void
    {
        $blade = (string) file_get_contents(base_path('routes/breadcrumbs.php'));

        $this->assertStringContainsString(
            "trail->push(__('web/activity-log.breadcrumb'), route('activity-logs.index'))",
            $blade,
            'activity-logs.index breadcrumb parent must expose a clickable route URL.',
        );
    }

    public function test_notifications_breadcrumb_parent_is_clickable(): void
    {
        $blade = (string) file_get_contents(base_path('routes/breadcrumbs.php'));

        $this->assertStringContainsString(
            "trail->push(__('web/notifications.breadcrumb'), route('notifications.index'))",
            $blade,
            'notifications.index breadcrumb parent must expose a clickable route URL.',
        );
    }

    public function test_profile_password_breadcrumb_parent_is_clickable(): void
    {
        $blade = (string) file_get_contents(base_path('routes/breadcrumbs.php'));

        $this->assertStringContainsString(
            "trail->push(__('web/profile.password_title'), route('profile.password.edit'))",
            $blade,
            'profile.password.edit breadcrumb parent must expose a clickable route URL.',
        );
    }

    public function test_file_hosting_empty_row_spans_all_columns(): void
    {
        $config = (string) file_get_contents(resource_path('views/adminlte4/file-hosting/index.blade.php'));
        $vue = (string) file_get_contents(resource_path('js/vue/islands/file-hosting/App.vue'));

        // Empty row colspan follows region visibility (7 without region column, 8 with).
        $this->assertStringContainsString(':colspan="config.showRegion ? 8 : 7"', $vue, 'Vue browse empty row must use dynamic region colspan.');
        $this->assertStringContainsString('$tableColspan = $showRegion ? 8 : 7', $config, 'file-hosting bootstrap must derive colspan from region visibility.');
        $this->assertStringNotContainsString('colspan="6"', $vue, 'file-hosting must not carry a stale colspan="6" empty row.');
    }

    public function test_file_hosting_toolbar_renders_symmetrical_border(): void
    {
        $css = (string) file_get_contents(resource_path('css/file-hosting/file-hosting.css'));

        $this->assertStringContainsString('.fh-toolbar {', $css);
        $this->assertMatchesRegularExpression(
            '/\.fh-toolbar\s*\{[^}]*\bborder:\s*1px solid[^}]*\}/s',
            $css,
            '.fh-toolbar must declare a full border (not border-bottom only) for visual symmetry.',
        );
    }

    public function test_user_activity_history_card_does_not_carry_top_margin(): void
    {
        $blade = (string) file_get_contents(resource_path('views/adminlte4/users/_activity_history.blade.php'));

        $this->assertStringNotContainsString('mt-3', $blade, 'activity history card must align flush with the edit-user card (no mt-3).');
    }

    public function test_user_activity_history_renders_empty_state_when_no_logs(): void
    {
        $blade = (string) file_get_contents(resource_path('views/adminlte4/users/_activity_history.blade.php'));

        $this->assertStringContainsString('@else', $blade, 'activity history partial must render an @else branch for the empty case.');
        $this->assertStringContainsString('empty-state', $blade, 'activity history partial must fall back to the empty-state component when no logs exist.');
    }

    public function test_menus_index_blade_ships_single_delete_button_per_row(): void
    {
        $blade = (string) file_get_contents(resource_path('views/adminlte4/menus/index.blade.php'));

        $this->assertStringNotContainsString("name=\"cascade\" value=\"1\"", $blade, 'menus index must not carry a second inline cascade submit button.');
        $this->assertStringContainsString('menuDeleteConfirmModal', $blade, 'menus index must expose the shared delete confirmation modal.');
        $this->assertStringContainsString('data-menu-cascade-url', $blade, 'menus index delete trigger must carry the cascade URL data attribute.');
        $this->assertStringContainsString('data-menu-confirm-template', $blade, 'menus index modal must expose the confirm text template via data attribute.');
    }

    public function test_vtual_confirm_modal_partial_exposes_aria_labelledby(): void
    {
        $blade = (string) file_get_contents(resource_path('views/adminlte4/vtual/partials/_confirm-modal.blade.php'));

        $this->assertStringContainsString('aria-labelledby="vtualConfirmLabel"', $blade);
        $this->assertStringContainsString('aria-hidden="true"', $blade);
        $this->assertStringContainsString('tabindex="-1"', $blade);
        $this->assertStringContainsString('btn-close', $blade, 'confirm modal must ship a dismiss button with aria-label.');
    }

    public function test_vtual_nav_partial_renders_aria_current_on_active_link(): void
    {
        $blade = (string) file_get_contents(resource_path('views/adminlte4/vtual/partials/nav.blade.php'));

        $this->assertStringContainsString('aria-current="page"', $blade);
        $this->assertStringContainsString('aria-label', $blade, 'nav partial must wrap items in an aria-label <nav>.');
    }

    public function test_vtual_status_badge_partial_renders_badge_class(): void
    {
        $blade = (string) file_get_contents(resource_path('views/adminlte4/components/vtual/status-badge.blade.php'));

        $this->assertStringContainsString('text-bg-success', $blade, 'active status must map to success badge.');
        $this->assertStringContainsString('text-bg-warning', $blade, 'pending status must map to warning badge.');
        $this->assertStringContainsString('text-bg-secondary', $blade, 'archived/disabled status must map to secondary badge.');
    }

    public function test_vtual_confirm_modal_js_does_not_use_native_confirm(): void
    {
        $js = (string) file_get_contents(resource_path('js/vtual/confirm-modal.js'));

        $this->assertStringNotContainsString('confirm(', $js, 'confirm-modal.js must not call native confirm().');
        $this->assertStringContainsString('requestSubmit', $js, 'confirm-modal.js must submit the AJAX form via requestSubmit().');
    }

    public function test_vtual_credential_form_js_toggles_secret_fields(): void
    {
        $js = (string) file_get_contents(resource_path('js/vtual/credential-form.js'));

        $this->assertStringContainsString('data-secret-fields', $js);
        $this->assertStringContainsString("classList.toggle('d-none'", $js);
    }

    /**
     * Auth shell is prefix('apps'). url('segment') resolves from site root and 404s
     * AJAX/forms. Ban known auth segments; use route() / route(... placeholder).
     */
    public function test_adminlte4_blades_do_not_url_auth_shell_segments(): void
    {
        // First path segment under Route::prefix('apps') — public root segments
        // (login, s, broadcasting) stay out so legitimate root url() is not banned.
        $segments = [
            'activity-logs',
            'dashboard',
            'domain-access',
            'error-logs',
            'file-hosting',
            'invoices',
            'inference',
            'lookups',
            'menus',
            'microstock',
            'navigation',
            'notifications',
            'payment-channel-routes',
            'payment-channels',
            'payment-gateways',
            'product-activations',
            'profile',
            'qr',
            'service-purchases',
            'services',
            'subscriptions',
            'users',
            'vtual',
        ];

        $pattern = "/url\\(\\s*['\"]\\/?(".implode('|', array_map('preg_quote', $segments)).")['\"]/";
        $root = resource_path('views/adminlte4');
        $violations = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            if (! preg_match_all($pattern, $contents, $matches)) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            foreach (array_unique($matches[0]) as $hit) {
                $violations[] = "{$relative}: {$hit}";
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Auth shell Blade must use route(), not url() for known segments:\n".implode("\n", $violations)
        );
    }

    public function test_file_hosting_router_js_fail_closed_without_hardcoded_apps_base(): void
    {
        $js = (string) file_get_contents(resource_path('js/file-hosting/router.js'));

        $this->assertStringContainsString('export function joinFhPath', $js, 'router.js must export joinFhPath for empty-base-safe joins.');
        $this->assertStringContainsString('export function resolveFhBase', $js);
        $this->assertStringNotContainsString('FH_AUTH_BASE_FALLBACK', $js, 'router.js must not hard-code FH auth base fallback.');
        $this->assertStringNotContainsString("'/apps/file-hosting'", $js, 'router.js must not hard-code /apps/file-hosting.');
        $this->assertStringNotContainsString('"/apps/file-hosting"', $js, 'router.js must not hard-code /apps/file-hosting.');
    }

    public function test_settings_form_renders_select_when_options_present(): void
    {
        $blade = (string) file_get_contents(resource_path('views/adminlte4/settings/_form.blade.php'));

        $this->assertStringContainsString("\$meta['options']", $blade);
        $this->assertStringContainsString('x-adminlte4::form.select', $blade);
        $this->assertStringContainsString(':select2="false"', $blade);
        $this->assertStringContainsString("web/settings.options.", $blade);
    }
}
