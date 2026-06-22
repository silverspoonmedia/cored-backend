<?php

namespace App\Services\Auth;

use App\Events\Auth\TwoFactorDisabled;
use App\Events\Auth\TwoFactorEnabled;
use App\Exceptions\Auth\InvalidTwoFactorCodeException;
use App\Exceptions\Auth\TwoFactorAlreadyEnabledException;
use App\Exceptions\Auth\TwoFactorNotEnabledException;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

class TwoFactorService
{
    /**
     * @return array<string, string>
     */
    public function startSetup(User $user): array
    {
        if ($this->enabled($user)) {
            throw new TwoFactorAlreadyEnabledException;
        }

        $secret = $this->google2fa()->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => null,
        ])->save();

        return $this->setupPayload($user, $secret);
    }

    /**
     * @return array<string, string>
     */
    public function confirmSetup(User $user, string $code): array
    {
        $secret = $this->secret($user);

        if ($secret === null) {
            throw new TwoFactorNotEnabledException;
        }

        if (! $this->verifyCode($secret, $code)) {
            throw new InvalidTwoFactorCodeException;
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        TwoFactorEnabled::dispatch(
            $user,
            request()->ip() ?? 'unknown',
            request()->userAgent(),
        );

        return ['confirmed_at' => $user->two_factor_confirmed_at->toIso8601String()];
    }

    public function disable(User $user, string $code): void
    {
        $secret = $this->secret($user);

        if ($secret === null || ! $this->enabled($user)) {
            throw new TwoFactorNotEnabledException;
        }

        if (! $this->verifyCode($secret, $code)) {
            throw new InvalidTwoFactorCodeException;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        TwoFactorDisabled::dispatch(
            $user,
            request()->ip() ?? 'unknown',
            request()->userAgent(),
        );
    }

    public function verifyLogin(User $user, string $code): bool
    {
        $secret = $this->secret($user);

        return $secret !== null
            && $this->enabled($user)
            && $this->verifyCode($secret, $code);
    }

    public function enabled(User $user): bool
    {
        return filled($user->two_factor_secret) && $user->two_factor_confirmed_at !== null;
    }

    private function verifyCode(string $secret, string $code): bool
    {
        return (bool) $this->google2fa()->verifyKey($secret, $code, (int) config('auth.two_factor.window', 1));
    }

    private function secret(User $user): ?string
    {
        if (! filled($user->two_factor_secret)) {
            return null;
        }

        return Crypt::decryptString($user->two_factor_secret);
    }

    /**
     * @return array<string, string>
     */
    private function setupPayload(User $user, string $secret): array
    {
        $issuer = (string) config('auth.two_factor.issuer', config('app.name', 'Monolith'));

        return [
            'secret' => $secret,
            'qr_code_url' => $this->google2fa()->getQRCodeUrl($issuer, $user->email, $secret),
            'qr_code_svg' => $this->google2fa()->getQRCodeInline($issuer, $user->email, $secret),
        ];
    }

    private function google2fa(): mixed
    {
        return app('pragmarx.google2fa');
    }
}
