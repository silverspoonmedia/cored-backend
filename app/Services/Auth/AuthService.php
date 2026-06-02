<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\InvalidCredentialsException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Auth\UserPresenter;
use Illuminate\Support\Facades\Auth;

class AuthService
{
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
     * @param  array{email: string, password: string}  $credentials
     * @return array<string, mixed>
     */
    public function login(array $credentials): array
    {
        if (! $token = Auth::guard('api')->attempt($credentials)) {
            throw new InvalidCredentialsException;
        }

        /** @var User $user */
        $user = Auth::guard('api')->user();

        return $this->buildAuthPayload($user, $token);
    }

    /**
     * @return array<string, mixed>
     */
    public function refresh(): array
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();
        $token = Auth::guard('api')->refresh();

        return $this->buildAuthPayload($user, $token);
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
     * @return array<string, mixed>
     */
    private function buildAuthPayload(User $user, ?string $token = null): array
    {
        $token ??= Auth::guard('api')->login($user);

        return [
            'user' => UserPresenter::present($user),
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => (int) config('jwt.ttl', 60) * 60,
        ];
    }
}
