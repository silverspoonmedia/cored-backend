<?php

namespace Tests\Unit\Support\Security;

use App\Support\Security\HcaptchaVerifier;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HcaptchaVerifierTest extends TestCase
{
    private HcaptchaVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verifier = new HcaptchaVerifier;
    }

    public function test_verify_returns_true_when_disabled_without_http_call(): void
    {
        config(['hcaptcha.enabled' => false]);

        Http::fake();

        $this->assertTrue($this->verifier->verify('any-token', '127.0.0.1'));

        Http::assertNothingSent();
    }

    public function test_verify_returns_false_for_empty_token_when_enabled(): void
    {
        config([
            'hcaptcha.enabled' => true,
            'hcaptcha.secret' => 'test-secret',
        ]);

        Http::fake();

        $this->assertFalse($this->verifier->verify('', '127.0.0.1'));

        Http::assertNothingSent();
    }

    public function test_verify_returns_false_when_enabled_without_secret(): void
    {
        config([
            'hcaptcha.enabled' => true,
            'hcaptcha.secret' => '',
        ]);

        Http::fake();

        $this->assertFalse($this->verifier->verify('token', '127.0.0.1'));

        Http::assertNothingSent();
    }

    public function test_verify_posts_to_hcaptcha_and_returns_true_on_success(): void
    {
        config([
            'hcaptcha.enabled' => true,
            'hcaptcha.secret' => 'test-secret',
            'hcaptcha.verify_url' => 'https://api.hcaptcha.com/siteverify',
        ]);

        Http::fake([
            'api.hcaptcha.com/siteverify' => Http::response(['success' => true], 200),
        ]);

        $this->assertTrue($this->verifier->verify('valid-token', '203.0.113.1'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.hcaptcha.com/siteverify'
                && $request['secret'] === 'test-secret'
                && $request['response'] === 'valid-token'
                && $request['remoteip'] === '203.0.113.1';
        });
    }

    public function test_verify_returns_false_when_hcaptcha_reports_failure(): void
    {
        config([
            'hcaptcha.enabled' => true,
            'hcaptcha.secret' => 'test-secret',
        ]);

        Http::fake([
            'api.hcaptcha.com/siteverify' => Http::response(['success' => false], 200),
        ]);

        $this->assertFalse($this->verifier->verify('invalid-token', '127.0.0.1'));
    }

    public function test_verify_returns_false_when_http_request_fails(): void
    {
        config([
            'hcaptcha.enabled' => true,
            'hcaptcha.secret' => 'test-secret',
        ]);

        Http::fake([
            'api.hcaptcha.com/siteverify' => Http::response([], 500),
        ]);

        $this->assertFalse($this->verifier->verify('token', '127.0.0.1'));
    }
}
