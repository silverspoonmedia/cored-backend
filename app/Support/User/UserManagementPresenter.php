<?php

namespace App\Support\User;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

class UserManagementPresenter
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
            'roles' => $user->roles->map(fn (Role $role) => [
                'name' => $role->name,
                'level' => $role->level,
            ])->values()->all(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, User>|iterable<int, User>  $users
     * @return list<array<string, mixed>>
     */
    public static function presentCollection(iterable $users): array
    {
        $presented = [];

        foreach ($users as $user) {
            $presented[] = self::present($user);
        }

        return $presented;
    }

    /**
     * @param  Collection<int, Role>  $roles
     * @return list<array<string, mixed>>
     */
    public static function presentAssignableRoles(Collection $roles): array
    {
        return $roles->map(fn (Role $role) => [
            'name' => $role->name,
            'level' => $role->level,
        ])->values()->all();
    }
}
