<?php

namespace Tests\Unit\Services\Auth;

use App\Exceptions\Auth\InvalidTwoFactorCodeException;
use App\Exceptions\Auth\TwoFactorAlreadyEnabledException;
use App\Exceptions\Auth\TwoFactorNotEnabledException;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\Auth\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class TwoFactorServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private TwoFactorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->service = new TwoFactorService;
    }

    public function test_start_setup_stores_encrypted_secret_and_returns_qr_url(): void
    {
        $user = User::factory()->create();

        $payload = $this->service->startSetup($user);

        $user->refresh();

        $this->assertNotEmpty($user->two_factor_secret);
        $this->assertTrue(str_starts_with($payload['qr_code_url'], 'otpauth://totp/'));
        $this->assertTrue(str_starts_with($payload['qr_code_svg'], 'data:image/svg+xml;base64,'));
        $this->assertSame(Crypt::decryptString($user->two_factor_secret), $payload['secret']);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_start_setup_throws_when_already_enabled(): void
    {
        $user = User::factory()->create([
            'two_factor_secret' => Crypt::encryptString('ABC123'),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->expectException(TwoFactorAlreadyEnabledException::class);

        $this->service->startSetup($user);
    }

    public function test_confirm_setup_with_valid_code_marks_confirmed(): void
    {
        $user = User::factory()->create();
        $setup = $this->service->startSetup($user);

        $code = app('pragmarx.google2fa')->getCurrentOtp($setup['secret']);

        $payload = $this->service->confirmSetup($user, $code);

        $user->refresh();

        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertNotEmpty($payload['confirmed_at']);
        $this->assertTrue($this->service->enabled($user));
    }

    public function test_confirm_setup_throws_for_invalid_code(): void
    {
        $user = User::factory()->create();
        $this->service->startSetup($user);

        $this->expectException(InvalidTwoFactorCodeException::class);

        $this->service->confirmSetup($user, '000000');
    }

    public function test_disable_removes_secret_and_timestamp(): void
    {
        $user = User::factory()->create();
        $setup = $this->service->startSetup($user);
        $code = app('pragmarx.google2fa')->getCurrentOtp($setup['secret']);
        $this->service->confirmSetup($user, $code);

        $code = app('pragmarx.google2fa')->getCurrentOtp($setup['secret']);
        $this->service->disable($user, $code);

        $user->refresh();

        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertFalse($this->service->enabled($user));
    }

    public function test_disable_throws_when_not_enabled(): void
    {
        $user = User::factory()->create();

        $this->expectException(TwoFactorNotEnabledException::class);

        $this->service->disable($user, '000000');
    }

    public function test_verify_login_returns_false_for_wrong_code(): void
    {
        $user = User::factory()->create();
        $setup = $this->service->startSetup($user);
        $code = app('pragmarx.google2fa')->getCurrentOtp($setup['secret']);
        $this->service->confirmSetup($user, $code);

        $this->assertFalse($this->service->verifyLogin($user, '000000'));
    }

    public function test_verify_login_returns_true_for_valid_code(): void
    {
        $user = User::factory()->create();
        $setup = $this->service->startSetup($user);
        $code = app('pragmarx.google2fa')->getCurrentOtp($setup['secret']);
        $this->service->confirmSetup($user, $code);

        $current = app('pragmarx.google2fa')->getCurrentOtp($setup['secret']);

        $this->assertTrue($this->service->verifyLogin($user, $current));
    }

    public function test_enabled_returns_false_when_not_confirmed(): void
    {
        $user = User::factory()->create([
            'two_factor_secret' => Crypt::encryptString('ABC123'),
            'two_factor_confirmed_at' => null,
        ]);

        $this->assertFalse($this->service->enabled($user));
    }
}
