<?php

namespace App\Services\User;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Rbac\RoleService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class UserManagementService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RoleService $roleService,
    ) {}

    /**
     * @return Collection<int, User>
     */
    public function list(User $actor): Collection
    {
        $actorLevel = $this->roleService->getLowestRoleLevel($actor);

        if ($actorLevel === null) {
            return new Collection;
        }

        return $this->users->listVisibleToActorLevel($actorLevel);
    }

    public function get(string $id): User
    {
        return $this->users->findByIdOrFailWithRoles($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): User
    {
        $roleName = $data['role'];
        $this->assertRoleAssignable($actor, $roleName);

        $user = $this->users->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->syncRoles([$roleName]);

        return $user->load('roles');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, string $id, array $data): User
    {
        if (isset($data['role'])) {
            $this->assertRoleAssignable($actor, $data['role']);
        }

        $attributes = Arr::only($data, ['name', 'email']);

        if (isset($data['password']) && $data['password'] !== '') {
            $attributes['password'] = Hash::make($data['password']);
        }

        $user = $this->users->update($id, $attributes);

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return $user->load('roles');
    }

    public function delete(User $actor, string $id): void
    {
        $this->users->softDelete($id);
    }

    private function assertRoleAssignable(User $actor, string $roleName): void
    {
        if (! $this->roleService->isRoleAssignableBy($actor, $roleName)) {
            abort(403, 'Role yang dipilih tidak boleh ditetapkan berdasarkan hierarki Anda.');
        }
    }
}
