<?php

namespace App\Support\User;

use App\Http\Resources\User\UserManagementResource;
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
        return (new UserManagementResource($user))->resolve(request());
    }

    /**
     * @param  Collection<int, User>|iterable<int, User>  $users
     * @return list<array<string, mixed>>
     */
    public static function presentCollection(iterable $users): array
    {
        return UserManagementResource::collection($users)->resolve(request());
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
