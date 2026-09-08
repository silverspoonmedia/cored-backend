<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ManagementMemberRole;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\ManagementMember;

class ManagementAccessService
{
    public function findOrFail(string $managementId): Management
    {
        return Management::query()->findOrFail($managementId);
    }

    public function roleFor(Management $management, User $actor): ?ManagementMemberRole
    {
        if ($actor->can('manage any vtual resource')) {
            return ManagementMemberRole::Owner;
        }

        /** @var ManagementMember|null $member */
        $member = ManagementMember::query()
            ->where('management_id', $management->id)
            ->where('user_id', $actor->id)
            ->first();

        return $member?->role;
    }

    public function canView(Management $management, User $actor): bool
    {
        if ($actor->can('view any vtual resource') || $actor->can('manage any vtual resource')) {
            return true;
        }

        $role = $this->roleFor($management, $actor);

        return $role !== null && $role->canView();
    }

    public function canMutate(Management $management, User $actor): bool
    {
        if ($actor->can('manage any vtual resource')) {
            return true;
        }

        $role = $this->roleFor($management, $actor);

        return $role !== null && $role->canMutate();
    }

    /**
     * Archiving (soft-deleting) a management org is reserved to org members
     * holding Owner or Admin. Deliberately does NOT route through roleFor():
     * that method implicitly treats internal staff (manage any vtual resource)
     * as Owner, while archiving must come from a real vtual_management_members
     * row only — internal staff without membership never get this power.
     */
    public function canArchive(Management $management, User $actor): bool
    {
        // Staff may clean up a mis-seeded claimable orphan org (no members
        // hold archive power over it, so the superuser path is the only way).
        if ($actor->can('manage any vtual resource')
            && app(ManagementService::class)->isClaimable($management)) {
            return true;
        }

        /** @var ManagementMember|null $member */
        $member = ManagementMember::query()
            ->where('management_id', $management->id)
            ->where('user_id', $actor->id)
            ->first();

        $role = $member?->role;

        return $role === ManagementMemberRole::Owner || $role === ManagementMemberRole::Admin;
    }

    public function canAssignCreators(Management $management, User $actor): bool
    {
        if ($actor->can('manage any vtual resource')) {
            return true;
        }

        $role = $this->roleFor($management, $actor);

        return $role !== null && $role->canAssignCreators();
    }

    public function canManageMembers(Management $management, User $actor): bool
    {
        if ($actor->can('manage any vtual resource')) {
            return true;
        }

        $role = $this->roleFor($management, $actor);

        return $role !== null && $role->canManageMembers();
    }

    public function assertCanView(Management $management, User $actor): void
    {
        if (! $this->canView($management, $actor)) {
            throw new VtualException(__('api/vtual.policy.access_denied'), 403);
        }
    }

    public function assertCanMutate(Management $management, User $actor): void
    {
        if (! $this->canMutate($management, $actor)) {
            throw new VtualException(__('api/vtual.policy.mutate_denied'), 403);
        }
    }

    public function assertCanArchive(Management $management, User $actor): void
    {
        if (! $this->canArchive($management, $actor)) {
            throw new VtualException(__('api/vtual.policy.archive_denied'), 403);
        }
    }

    public function assertCanAssignCreators(Management $management, User $actor): void
    {
        if (! $this->canAssignCreators($management, $actor)) {
            throw new VtualException(__('api/vtual.policy.assign_denied'), 403);
        }
    }

    public function assertCanManageMembers(Management $management, User $actor): void
    {
        if (! $this->canManageMembers($management, $actor)) {
            throw new VtualException(__('api/vtual.policy.members_denied'), 403);
        }
    }
}
