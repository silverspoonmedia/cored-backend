<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class HcaptchaConfigTest extends TestCase
{
    public function test_defaults_to_disabled_with_null_credentials(): void
    {
        config([
            'hcaptcha.enabled' => false,
            'hcaptcha.secret' => null,
            'hcaptcha.sitekey' => null,
        ]);

        $this->assertFalse(config('hcaptcha.enabled'));
        $this->assertNull(config('hcaptcha.secret'));
        $this->assertNull(config('hcaptcha.sitekey'));
        $this->assertSame('https://api.hcaptcha.com/siteverify', config('hcaptcha.verify_url'));
    }

    public function test_values_can_be_overridden(): void
    {
        config([
            'hcaptcha.enabled' => true,
            'hcaptcha.secret' => 'test-secret',
            'hcaptcha.sitekey' => 'test-sitekey',
        ]);

        $this->assertTrue(config('hcaptcha.enabled'));
        $this->assertSame('test-secret', config('hcaptcha.secret'));
        $this->assertSame('test-sitekey', config('hcaptcha.sitekey'));
    }
}
