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

    public function canAssignCreators(Management $management, User $actor): bool
    {
        if ($actor->can('manage any vtual resource')) {
            return true;
        }

        $role = $this->roleFor($management, $actor);

        return $role !== null && $role->canAssignCreators();
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

    public function assertCanAssignCreators(Management $management, User $actor): void
    {
        if (! $this->canAssignCreators($management, $actor)) {
            throw new VtualException(__('api/vtual.policy.assign_denied'), 403);
        }
    }
}
