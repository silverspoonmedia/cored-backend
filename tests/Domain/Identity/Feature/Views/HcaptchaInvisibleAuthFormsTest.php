<?php

namespace Tests\Domain\Identity\Feature\Views;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HcaptchaInvisibleAuthFormsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1?: array<string, mixed>}>
     */
    public static function authFormRoutesProvider(): array
    {
        return [
            'login' => ['login'],
            'register' => ['register'],
            'forgot-password' => ['password.request'],
            'two-factor-challenge' => ['two-factor.challenge', ['two_factor_token' => 'fake-token']],
        ];
    }

    #[DataProvider('authFormRoutesProvider')]
    public function test_auth_forms_render_invisible_hcaptcha_when_enabled(string $routeName, array $session = []): void
    {
        config([
            'hcaptcha.enabled' => true,
            'hcaptcha.sitekey' => 'test-sitekey',
        ]);

        $response = $this->withSession($session)->get(route($routeName));

        $response->assertOk();
        $response->assertSee('data-hcaptcha-widget', false);
        $response->assertSee('render=explicit', false);
        $response->assertSee('MonolithHcaptcha', false);
        $response->assertDontSee('fitHcaptchaField', false);
        $response->assertDontSee('data-size="normal"', false);
    }

    public function test_login_does_not_render_hcaptcha_when_disabled(): void
    {
        config([
            'hcaptcha.enabled' => false,
            'hcaptcha.sitekey' => 'test-sitekey',
        ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('https://js.hcaptcha.com/1/api.js', false)
            ->assertDontSee('onHcaptchaApiLoad', false);
    }
}
