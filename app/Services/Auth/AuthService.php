<?php

namespace App\Services\Auth;

use App\Events\Auth\LoginFailed;
use App\Events\Auth\LoginSuccessful;
use App\Events\Auth\UserLoggedOut;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\InvalidTwoFactorCodeException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Auth\UserPresenter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AuthService
{
    private const TWO_FACTOR_CACHE_PREFIX = '2fa_token:';

    private const TWO_FACTOR_CACHE_TTL = 300;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly TwoFactorService $twoFactor,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     * @return array<string, mixed>
     */
    public function register(array $data): array
    {
        $user = $this->users->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->assignRole(config('auth.default_role', 'User'));
        $user->sendEmailVerificationNotification();

        return $this->buildAuthPayload($user);
    }

    /**
     * @param  array{email: string, password: string, remember_me?: bool}  $credentials
     * @return array<string, mixed>
     */
    public function login(array $credentials): array
    {
        $remember = (bool) ($credentials['remember_me'] ?? false);
        $credentials = Arr::only($credentials, ['email', 'password']);

        try {
            return $this->withTokenLifetime($remember, function (int $ttlMinutes) use ($credentials, $remember) {
                Auth::forgetGuards();
                $guard = $this->guard();
                $guard->setTTL($ttlMinutes);

                if (! $token = $guard->attempt($credentials)) {
                    throw new InvalidCredentialsException;
                }

                /** @var User $user */
                $user = $guard->user();

                if ($this->twoFactor->enabled($user)) {
                    $guard->setToken($token)->invalidate();

                    return $this->buildTwoFactorChallengePayload($user, $remember);
                }

                LoginSuccessful::dispatch(
                    $user,
                    request()->ip() ?? 'unknown',
                    request()->userAgent()
                );

                return $this->buildAuthPayload($user, $token, $ttlMinutes, $remember);
            });
        } catch (InvalidCredentialsException $e) {
            LoginFailed::dispatch(
                $credentials['email'] ?? 'unknown',
                request()->ip() ?? 'unknown',
                request()->userAgent()
            );

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function refresh(): array
    {
        $guard = $this->guard();

        return $this->withTokenLifetime(false, function (int $ttlMinutes) use ($guard) {
            Auth::forgetGuards();
            $guard = $this->guard();
            $guard->setTTL($ttlMinutes);

            /** @var User $user */
            $user = $guard->user();
            $token = $guard->refresh();

            return $this->buildAuthPayload($user, $token, $ttlMinutes, false);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function loginWithTwoFactorToken(string $twoFactorToken, string $code): array
    {
        $challenge = Cache::pull($this->twoFactorCacheKey($twoFactorToken));

        if (! is_array($challenge) || ! isset($challenge['user_id'], $challenge['remember'])) {
            throw new InvalidTwoFactorCodeException;
        }

        $user = User::query()->find((string) $challenge['user_id']);

        if (! $user || ! $this->twoFactor->verifyLogin($user, $code)) {
            throw new InvalidTwoFactorCodeException;
        }

        LoginSuccessful::dispatch(
            $user,
            request()->ip() ?? 'unknown',
            request()->userAgent()
        );

        return $this->withTokenLifetime((bool) $challenge['remember'], function (int $ttlMinutes) use ($user, $challenge) {
            Auth::forgetGuards();
            $guard = $this->guard();
            $guard->setTTL($ttlMinutes);

            $token = $guard->login($user);

            return $this->buildAuthPayload($user, $token, $ttlMinutes, (bool) $challenge['remember']);
        });
    }

    public function logout(): void
    {
        /** @var User|null $user */
        $user = Auth::guard('api')->user();

        Auth::guard('api')->logout();

        if ($user) {
            UserLoggedOut::dispatch(
                $user,
                request()->ip() ?? 'unknown'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function me(): array
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        return UserPresenter::present($user);
    }

    /**
     * @param  callable(int): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function withTokenLifetime(bool $remember, callable $callback): array
    {
        $defaultTtl = $this->defaultTtlMinutes();
        $ttlMinutes = $remember ? $this->rememberTtlMinutes() : $defaultTtl;

        try {
            return $callback($ttlMinutes);
        } finally {
            Auth::forgetGuards();
            app()->forgetInstance('tymon.jwt');
            app()->forgetInstance('tymon.jwt.provider.jwt');
            app()->forgetInstance('tymon.jwt.manager');
        }
    }

    private function guard(): JWTGuard
    {
        /** @var JWTGuard $guard */
        $guard = Auth::guard('api');

        return $guard;
    }

    private function defaultTtlMinutes(): int
    {
        return (int) config('jwt.ttl', 60);
    }

    private function rememberTtlMinutes(): int
    {
        return (int) config('jwt.remember_ttl', config('jwt.refresh_ttl', 20160));
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function buildTwoFactorChallengePayload(User $user, bool $remember): array
    {
        $twoFactorToken = (string) Str::uuid();

        Cache::put(
            $this->twoFactorCacheKey($twoFactorToken),
            ['user_id' => $user->id, 'remember' => $remember],
            self::TWO_FACTOR_CACHE_TTL,
        );

        return [
            'user' => UserPresenter::present($user),
            'two_factor_required' => true,
            'two_factor_token' => $twoFactorToken,
            'two_factor_token_expires_in' => self::TWO_FACTOR_CACHE_TTL,
            'remember' => $remember,
        ];
    }

    private function twoFactorCacheKey(string $token): string
    {
        return self::TWO_FACTOR_CACHE_PREFIX.$token;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAuthPayload(
        User $user,
        ?string $token = null,
        ?int $ttlMinutes = null,
        bool $remember = false,
    ): array {
        if ($token === null) {
            return $this->withTokenLifetime($remember, function (int $ttl) use ($user, $ttlMinutes, $remember) {
                Auth::forgetGuards();
                $guard = $this->guard();
                $guard->setTTL($ttlMinutes ?? $ttl);
                $token = $guard->login($user);
                $actualTtl = $ttlMinutes ?? $guard->getTTL() ?? $this->defaultTtlMinutes();

                return [
                    'user' => UserPresenter::present($user),
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => $actualTtl * 60,
                    'remember' => $remember,
                ];
            });
        }

        $ttlMinutes ??= $this->defaultTtlMinutes();

        return [
            'user' => UserPresenter::present($user),
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $ttlMinutes * 60,
            'remember' => $remember,
        ];
    }
}
