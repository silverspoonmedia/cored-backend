<?php

namespace Tests\Unit\Rules;

use App\Rules\Hcaptcha;
use App\Support\Security\HcaptchaVerifier;
use Illuminate\Support\Facades\Validator;
use Mockery;
use Tests\TestCase;

class HcaptchaTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_rule_passes_when_verifier_accepts_token(): void
    {
        $verifier = Mockery::mock(HcaptchaVerifier::class);
        $verifier->shouldReceive('verify')
            ->once()
            ->with('valid-token', Mockery::any())
            ->andReturn(true);

        $validator = Validator::make(
            ['h-captcha-response' => 'valid-token'],
            ['h-captcha-response' => [new Hcaptcha($verifier)]],
        );

        $this->assertFalse($validator->fails());
    }

    public function test_rule_fails_with_translated_message_when_verifier_rejects_token(): void
    {
        $verifier = Mockery::mock(HcaptchaVerifier::class);
        $verifier->shouldReceive('verify')
            ->once()
            ->with('bad-token', Mockery::any())
            ->andReturn(false);

        $validator = Validator::make(
            ['h-captcha-response' => 'bad-token'],
            ['h-captcha-response' => [new Hcaptcha($verifier)]],
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            __('validation.hcaptcha'),
            $validator->errors()->first('h-captcha-response'),
        );
    }
}
