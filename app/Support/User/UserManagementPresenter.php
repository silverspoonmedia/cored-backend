<?php

namespace App\Support\User;

use App\Domain\Identity\Http\Resources\UserManagementResource;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Collection;

class UserManagementPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(User $user): array
    {
        return (new UserManagementResource($user))->resolve(request());
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
