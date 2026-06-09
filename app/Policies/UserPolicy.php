<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Rbac\RoleService;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly RoleService $roleService,
    ) {}

    public function viewAny(User $actor): Response
    {
        return $this->checkPermission($actor, 'view user');
    }

    public function view(User $actor, User $target): Response
    {
        if ($actor->is($target)) {
            return $this->allow();
        }

        $permission = $this->checkPermission($actor, 'view user');

        if ($permission->denied()) {
            return $permission;
        }

        return $this->checkHierarchy($actor, $target);
    }

    public function create(User $actor): Response
    {
        return $this->checkPermission($actor, 'create user');
    }

    public function update(User $actor, User $target): Response
    {
        $permission = $this->checkPermission($actor, 'edit user');

        if ($permission->denied()) {
            return $permission;
        }

        return $this->checkAllRolesAbove($actor, $target);
    }

    public function delete(User $actor, User $target): Response
    {
        $permission = $this->checkPermission($actor, 'delete user');

        if ($permission->denied()) {
            return $permission;
        }

        return $this->checkAllRolesAbove($actor, $target);
    }

    public function assignableRoles(User $actor): Response
    {
        if ($actor->can('create user') || $actor->can('edit user')) {
            return $this->allow();
        }

        return $this->deny('Anda tidak memiliki izin untuk melihat daftar role yang dapat ditetapkan.');
    }

    private function checkPermission(User $actor, string $permission): Response
    {
        if ($actor->can($permission)) {
            return $this->allow();
        }

        $message = match ($permission) {
            'view user' => 'Anda tidak memiliki izin untuk melihat pengguna.',
            'create user' => 'Anda tidak memiliki izin untuk membuat pengguna.',
            'edit user' => 'Anda tidak memiliki izin untuk mengedit pengguna.',
            'delete user' => 'Anda tidak memiliki izin untuk menghapus pengguna.',
            default => 'Anda tidak memiliki izin untuk melakukan tindakan ini.',
        };

        return $this->deny($message);
    }

    private function checkHierarchy(User $actor, User $target): Response
    {
        if ($this->roleService->canActorViewTarget($actor, $target)) {
            return $this->allow();
        }

        return $this->deny(
            'Anda tidak dapat mengakses pengguna dengan level role lebih tinggi dari Anda.',
        );
    }

    private function checkAllRolesAbove(User $actor, User $target): Response
    {
        if ($this->roleService->allTargetRolesAboveActor($actor, $target)) {
            return $this->allow();
        }

        return $this->deny(
            'Anda tidak dapat mengubah pengguna dengan level role yang sama atau lebih tinggi dari Anda.',
        );
    }
}
