<?php

namespace Tests\Feature\Http\Controllers\Web;

use Tests\TestCase;

/**
 * Verifies the Monolith soft-override CSS layer ships the design tokens
 * and component overrides needed for light-mode polish.
 *
 * The override layer lives in two mirror files:
 *   - resources/css/monolith-overrides.css  (loaded on every Blade page)
 *   - resources/css/app.css                 (Tailwind entry, same tokens)
 *
 * Both must stay in sync. These tests guard against accidental token drift
 * and ensure the light-mode accent system is present.
 */
class ThemeOverrideCssTest extends TestCase
{
    /** @return list<string> */
    private function overrideFiles(): array
    {
        return [
            'monolith-overrides' => resource_path('css/monolith-overrides.css'),
            'app' => resource_path('css/app.css'),
        ];
    }

    public function test_override_css_file_exists_and_is_readable(): void
    {
        foreach ($this->overrideFiles() as $label => $path) {
            $this->assertFileExists($path, "{$label} CSS missing.");
            $this->assertNotEmpty(file_get_contents($path), "{$label} CSS is empty.");
        }
    }

    public function test_light_mode_accent_tokens_are_defined_in_root(): void
    {
        foreach ($this->overrideFiles() as $label => $path) {
            $css = file_get_contents($path);

            $this->assertStringContainsString('--monolith-accent:', $css, "{$label} missing --monolith-accent token.");
            $this->assertStringContainsString('--monolith-accent-soft:', $css, "{$label} missing --monolith-accent-soft token.");
            $this->assertStringContainsString('--monolith-accent-soft-hover:', $css, "{$label} missing --monolith-accent-soft-hover token.");
        }
    }

    public function test_light_mode_accent_uses_indigo_value(): void
    {
        foreach ($this->overrideFiles() as $label => $path) {
            $css = file_get_contents($path);

            // Light mode accent must be indigo (#6366f1) — adds life to the slate palette
            $this->assertStringContainsString('--monolith-accent: #6366f1;', $css, "{$label} light accent is not indigo #6366f1.");
        }
    }

    public function test_dark_mode_accent_uses_lighter_indigo(): void
    {
        foreach ($this->overrideFiles() as $label => $path) {
            $css = file_get_contents($path);

            // Dark mode accent is lighter indigo (#818cf8) for contrast on dark surfaces
            $this->assertStringContainsString('--monolith-accent: #818cf8;', $css, "{$label} dark accent is not lighter indigo #818cf8.");
        }
    }

    public function test_light_mode_shadow_is_stronger_than_original(): void
    {
        foreach ($this->overrideFiles() as $label => $path) {
            $css = file_get_contents($path);

            // Original shadow-sm was 0.04/0.06; improved is 0.06/0.08 so cards have visible lift
            $this->assertStringContainsString('rgba(15, 23, 42, 0.06)', $css, "{$label} missing strengthened light shadow-sm.");
            $this->assertStringNotContainsString('rgba(15, 23, 42, 0.04)', $css, "{$label} still has the old too-weak shadow-sm.");
        }
    }

    public function test_light_mode_border_is_more_visible(): void
    {
        foreach ($this->overrideFiles() as $label => $path) {
            $css = file_get_contents($path);

            // Original border-subtle was 0.08 (barely visible on white); improved is 0.12.
            // Assert against the token declaration line, not the whole file (shadow-lg also
            // legitimately uses 0.08 opacity).
            $this->assertStringContainsString('--monolith-border-subtle: rgba(15, 23, 42, 0.12);', $css, "{$label} missing strengthened light border token.");
            $this->assertStringNotContainsString('--monolith-border-subtle: rgba(15, 23, 42, 0.08);', $css, "{$label} still has the old too-faint border token.");
        }
    }

    public function test_login_page_gradient_uses_token_driven_values(): void
    {
        foreach ($this->overrideFiles() as $label => $path) {
            $css = file_get_contents($path);

            // Gradient is now token-driven: var(--monolith-gradient-start/mid/end)
            $this->assertStringContainsString('var(--monolith-gradient-start)', $css, "{$label} missing gradient-start token usage.");
            $this->assertStringContainsString('var(--monolith-gradient-mid)', $css, "{$label} missing gradient-mid token usage.");
            $this->assertStringContainsString('var(--monolith-gradient-end)', $css, "{$label} missing gradient-end token usage.");
            // Old hardcoded hex gradient must be gone
            $this->assertStringNotContainsString('#ecfdf5', $css, "{$label} still has old hardcoded gradient hex.");
        }
    }

    public function test_interactive_states_use_accent_tint(): void
    {
        foreach ($this->overrideFiles() as $label => $path) {
            $css = file_get_contents($path);

            // Sidebar, table hover, dropdown, list-group should use accent-soft tint
            $this->assertStringContainsString('var(--monolith-accent-soft)', $css, "{$label} missing accent-soft usage in interactive states.");
        }
    }

    public function test_sidebar_active_link_has_accent_left_indicator(): void
    {
        foreach ($this->overrideFiles() as $label => $path) {
            $css = file_get_contents($path);

            // Active nav link gets a left accent bar for wayfinding
            $this->assertStringContainsString('inset 2px 0 0 var(--monolith-accent)', $css, "{$label} missing active sidebar accent indicator.");
        }
    }

    public function test_card_hover_lifts_slightly(): void
    {
        foreach ($this->overrideFiles() as $label => $path) {
            $css = file_get_contents($path);

            // Card hover includes translateY(-1px) for tactile lift
            $this->assertStringContainsString('translateY(-1px)', $css, "{$label} missing card hover lift.");
        }
    }

    public function test_login_page_renders_with_override_css_asset_link(): void
    {
        // The login page must load the CSS override layer via @vite.
        // Vite produces a hashed filename (monolith-overrides-<hash>.css), so we
        // assert against the stable base name, not the literal source path.
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('monolith-overrides', false);
        $response->assertSee('.css', false);
    }

    public function test_login_page_renders_theme_toggle_script(): void
    {
        // Both layouts (app + auth) load assets-scripts.blade.php, which contains
        // the theme toggle JS (lte-theme localStorage + data-bs-theme-value).
        // The login page (auth layout, bare) still gets the theme script even
        // though it has no navbar toggle — so the user's persisted theme applies.
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('lte-theme', false);
        $response->assertSee('data-bs-theme', false);
    }

    // ---- Counterpart parity tests ----
    // Every token defined in :root (light) must also be defined in
    // [data-bs-theme="dark"], and vice versa. No standalone implementations.

    /** @return list<string> */
    private function counterpartTokens(): array
    {
        return [
            '--monolith-text-body',
            '--monolith-text-code',
            '--monolith-focus-color',
            '--monolith-focus-ring',
            '--monolith-gradient-start',
            '--monolith-gradient-mid',
            '--monolith-gradient-end',
            '--monolith-status-secondary-bg',
            '--monolith-status-secondary-text',
            '--monolith-status-warning-bg',
            '--monolith-status-warning-text',
            '--monolith-status-success-bg',
            '--monolith-status-success-text',
            '--monolith-status-danger-bg',
            '--monolith-status-danger-text',
            '--monolith-kind-qris-bg',
            '--monolith-kind-qris-text',
            '--monolith-kind-va-bg',
            '--monolith-kind-va-text',
            '--monolith-kind-ewallet-bg',
            '--monolith-kind-ewallet-text',
        ];
    }

    public function test_counterpart_tokens_defined_in_light_root(): void
    {
        foreach ($this->overrideFiles() as $label => $path) {
            $css = file_get_contents($path);

            foreach ($this->counterpartTokens() as $token) {
                $this->assertStringContainsString(
                    "{$token}:",
                    $css,
                    "{$label} missing light-mode token {$token} in :root.",
                );
            }
        }
    }

    public function test_counterpart_tokens_defined_in_dark_block(): void
    {
        foreach ($this->overrideFiles() as $label => $path) {
            $css = file_get_contents($path);

            foreach ($this->counterpartTokens() as $token) {
                $this->assertStringContainsString(
                    "{$token}:",
                    $css,
                    "{$label} missing dark-mode token {$token} in [data-bs-theme=dark].",
                );
            }
        }
    }

    public function test_focus_rings_use_token_not_hardcoded_hex(): void
    {
        foreach ($this->overrideFiles() as $label => $path) {
            $css = file_get_contents($path);

            // Focus rules must reference var(--monolith-focus-color), not #6366f1
            $this->assertStringContainsString('var(--monolith-focus-color)', $css, "{$label} focus rules must use --monolith-focus-color token.");
            $this->assertStringContainsString('var(--monolith-focus-ring)', $css, "{$label} focus ring must use --monolith-focus-ring token.");
        }
    }

    public function test_no_hardcoded_focus_hex_in_component_rules(): void
    {
        foreach ($this->overrideFiles() as $label => $path) {
            $css = file_get_contents($path);

            // The only allowed #6366f1 occurrences are token definitions, not component rules.
            // Token definitions look like: --monolith-accent: #6366f1; or --monolith-focus-color: #6366f1;
            // Component rules like border-color: #6366f1; are forbidden.
            preg_match_all('/^[^\/\s][^:]*:\s*#6366f1/m', $css, $matches, PREG_OFFSET_CAPTURE);
            $violations = array_filter($matches[0] ?? [], fn ($m) => ! str_starts_with(trim($m[0]), '--'));
            $this->assertEmpty(
                $violations,
                "{$label} has hardcoded #6366f1 in component rules (must use var(--monolith-focus-color)): " . implode(', ', array_column($violations, 0)),
            );
        }
    }

    public function test_login_gradient_uses_var_not_hex(): void
    {
        foreach ($this->overrideFiles() as $label => $path) {
            $css = file_get_contents($path);

            // The .login-page rule must use var() for the gradient, not hardcoded hex
            $this->assertStringContainsString('linear-gradient(135deg, var(--monolith-gradient-start)', $css, "{$label} login gradient must use token vars.");
        }
    }

    public function test_light_mode_text_normalization_block_exists(): void
    {
        foreach ($this->overrideFiles() as $label => $path) {
            $css = file_get_contents($path);

            // Light mode must explicitly set body text color (counterpart to dark block)
            $this->assertStringContainsString('body {', $css, "{$label} missing body rule.");
            $this->assertStringContainsString('color: var(--monolith-text-body);', $css, "{$label} missing light-mode body text normalization.");
            $this->assertStringContainsString('color: var(--monolith-text-code);', $css, "{$label} missing light-mode code color normalization.");
        }
    }

    public function test_dark_mode_uses_text_body_token_not_hex(): void
    {
        foreach ($this->overrideFiles() as $label => $path) {
            $css = file_get_contents($path);

            // Dark mode text rules must use var(--monolith-text-body), not #e2e8f0
            // The only allowed #e2e8f0 is the token definition itself
            $this->assertStringContainsString('--monolith-text-body: #e2e8f0;', $css, "{$label} must define --monolith-text-body token.");
            // No component-rule usage of #e2e8f0 (only token definition allowed)
            preg_match_all('/^[^\/\s][^:]*:\s*#e2e8f0/m', $css, $matches, PREG_OFFSET_CAPTURE);
            $violations = array_filter($matches[0] ?? [], fn ($m) => ! str_starts_with(trim($m[0]), '--'));
            $this->assertEmpty(
                $violations,
                "{$label} has hardcoded #e2e8f0 in component rules (must use var(--monolith-text-body)): " . implode(', ', array_column($violations, 0)),
            );
        }
    }

    public function test_text_strong_applied_to_headings(): void
    {
        foreach ($this->overrideFiles() as $label => $path) {
            $css = file_get_contents($path);

            // Headings must use --monolith-text-strong for consistent strong text in both modes
            $this->assertStringContainsString('.app-content-header h3', $css, "{$label} missing app-content-header h3 rule.");
            $this->assertStringContainsString('color: var(--monolith-text-strong);', $css, "{$label} missing --monolith-text-strong on headings.");
        }
    }

    public function test_payment_css_kind_icons_are_token_driven(): void
    {
        $files = [
            'channels-admin' => [resource_path('css/payments/channels-admin.css'), '--pc-kind-'],
            'order-invoice-show' => [resource_path('css/payments/order-invoice-show.css'), '--ois-kind-'],
        ];

        foreach ($files as $label => [$path, $prefix]) {
            $this->assertFileExists($path, "{$label} CSS missing.");
            $css = file_get_contents($path);

            $this->assertStringContainsString("{$prefix}qris-bg:", $css, "{$label} missing kind-qris-bg token.");
            $this->assertStringContainsString("{$prefix}va-bg:", $css, "{$label} missing kind-va-bg token.");
            $this->assertStringContainsString("{$prefix}ewallet-bg:", $css, "{$label} missing kind-ewallet-bg token.");
        }
    }

    public function test_payment_css_status_colors_are_token_driven(): void
    {
        $files = [
            'channels-admin' => resource_path('css/payments/channels-admin.css'),
            'order-invoice-show' => resource_path('css/payments/order-invoice-show.css'),
        ];

        foreach ($files as $label => $path) {
            $css = file_get_contents($path);

            $prefix = $label === 'channels-admin' ? '--pc-status-' : '--ois-status-';
            $this->assertStringContainsString("{$prefix}secondary-bg:", $css, "{$label} missing status-secondary-bg token.");
            $this->assertStringContainsString("{$prefix}warning-bg:", $css, "{$label} missing status-warning-bg token.");
            $this->assertStringContainsString("{$prefix}success-bg:", $css, "{$label} missing status-success-bg token.");
            $this->assertStringContainsString("{$prefix}danger-bg:", $css, "{$label} missing status-danger-bg token.");
        }
    }

    public function test_payment_css_gradients_are_token_driven(): void
    {
        $files = [
            'channels-admin' => resource_path('css/payments/channels-admin.css'),
            'order-invoice-show' => resource_path('css/payments/order-invoice-show.css'),
        ];

        foreach ($files as $label => $path) {
            $css = file_get_contents($path);

            $prefix = $label === 'channels-admin' ? '--pc-intro-gradient-' : '--ois-hero-gradient-';
            $this->assertStringContainsString("{$prefix}start:", $css, "{$label} missing gradient-start token.");
            $this->assertStringContainsString("{$prefix}mid:", $css, "{$label} missing gradient-mid token.");
            $this->assertStringContainsString("{$prefix}end:", $css, "{$label} missing gradient-end token.");
        }
    }
}
