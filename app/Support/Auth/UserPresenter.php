<?php

namespace App\Support\Auth;

use App\Domain\Identity\Models\User;

class UserPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'two_factor_enabled' => $user->hasTwoFactorSecret() && $user->two_factor_confirmed_at !== null,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ];
    }
}
