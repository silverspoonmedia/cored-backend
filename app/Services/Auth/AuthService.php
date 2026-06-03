<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\InvalidCredentialsException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Auth\UserPresenter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AuthService
{
    private const REMEMBER_CLAIM = 'rm';

    public function __construct(
        private readonly UserRepositoryInterface $users,
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

        $user->assignRole(config('auth.default_role', 'user'));
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

        return $this->withTokenLifetime($remember, function (JWTGuard $guard, int $ttlMinutes) use ($credentials, $remember) {
            if (! $token = $guard->attempt($credentials)) {
                throw new InvalidCredentialsException;
            }

            /** @var User $user */
            $user = $guard->user();

            return $this->buildAuthPayload($user, $token, $ttlMinutes, $remember);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function refresh(): array
    {
        $guard = $this->guard();
        $remember = (bool) $guard->payload()->get(self::REMEMBER_CLAIM, false);

        return $this->withTokenLifetime($remember, function (JWTGuard $guard, int $ttlMinutes) use ($remember) {
            /** @var User $user */
            $user = $guard->user();
            $token = $guard->refresh();

            return $this->buildAuthPayload($user, $token, $ttlMinutes, $remember);
        });
    }

    public function logout(): void
    {
        Auth::guard('api')->logout();
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
     * @param  callable(JWTGuard, int): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function withTokenLifetime(bool $remember, callable $callback): array
    {
        $guard = $this->guard();
        $defaultTtl = $this->defaultTtlMinutes();
        $ttlMinutes = $remember ? $this->rememberTtlMinutes() : $defaultTtl;

        if ($remember) {
            $guard->claims([self::REMEMBER_CLAIM => true]);
        }

        $guard->setTTL($ttlMinutes);

        try {
            return $callback($guard, $ttlMinutes);
        } finally {
            $guard->setTTL($defaultTtl);
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
    private function buildAuthPayload(
        User $user,
        ?string $token = null,
        ?int $ttlMinutes = null,
        bool $remember = false,
    ): array {
        $guard = $this->guard();
        $token ??= $guard->login($user);
        $ttlMinutes ??= $guard->getTTL() ?? $this->defaultTtlMinutes();

        return [
            'user' => UserPresenter::present($user),
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $ttlMinutes * 60,
            'remember' => $remember,
        ];
    }
}
