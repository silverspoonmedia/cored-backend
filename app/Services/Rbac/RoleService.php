<?php

namespace App\Services\Rbac;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class RoleService
{
    /**
     * Level terendah (angka terkecil) = kewenangan tertinggi.
     */
    public function getLowestRoleLevel(User $user): ?int
    {
        $levels = $this->getRoleLevels($user);

        if ($levels->isEmpty()) {
            return null;
        }

        return $levels->min();
    }

    /**
     * @return Collection<int, int>
     */
    public function getRoleLevels(User $user): Collection
    {
        return $user->roles()
            ->whereNotNull('level')
            ->pluck('level')
            ->map(fn ($level) => (int) $level);
    }

    /**
     * @return EloquentCollection<int, Role>
     */
    public function getAssignableRoles(User $actor): EloquentCollection
    {
        $actorLevel = $this->getLowestRoleLevel($actor);

        if ($actorLevel === null) {
            return Role::query()->whereRaw('1 = 0')->get();
        }

        return Role::query()
            ->where('guard_name', $actor->guard_name ?? 'api')
            ->where('level', '>', $actorLevel)
            ->orderBy('level')
            ->get();
    }

    public function isRoleAssignableBy(User $actor, string $roleName): bool
    {
        return $this->getAssignableRoles($actor)
            ->contains(fn (Role $role) => $role->name === $roleName);
    }

    /**
     * Untuk view/index/show: level efektif target (terendah) harus lebih besar dari actor.
     */
    public function canActorViewTarget(User $actor, User $target): bool
    {
        $actorLevel = $this->getLowestRoleLevel($actor);
        $targetLevel = $this->getLowestRoleLevel($target);

        if ($actorLevel === null || $targetLevel === null) {
            return false;
        }

        return $targetLevel > $actorLevel;
    }

    /**
     * Untuk edit/delete: seluruh role target harus berlevel lebih besar dari actor.
     */
    public function allTargetRolesAboveActor(User $actor, User $target): bool
    {
        $actorLevel = $this->getLowestRoleLevel($actor);

        if ($actorLevel === null) {
            return false;
        }

        $targetLevels = $this->getRoleLevels($target);

        if ($targetLevels->isEmpty()) {
            return false;
        }

        return $targetLevels->every(fn (int $level) => $level > $actorLevel);
    }
}
