<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\CreatorMemberRole;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\CreatorMember;

class CreatorAccessService
{
    public function findOrFail(string $creatorId): Creator
    {
        return Creator::query()->findOrFail($creatorId);
    }

    public function roleFor(Creator $creator, User $actor): ?CreatorMemberRole
    {
        if ($actor->can('manage any vtual resource')) {
            return CreatorMemberRole::Owner;
        }

        /** @var CreatorMember|null $member */
        $member = CreatorMember::query()
            ->where('creator_id', $creator->id)
            ->where('user_id', $actor->id)
            ->first();

        return $member?->role;
    }

    public function canView(Creator $creator, User $actor): bool
    {
        if ($actor->can('view any vtual resource') || $actor->can('manage any vtual resource')) {
            return true;
        }

        $role = $this->roleFor($creator, $actor);

        return $role !== null && $role->canView();
    }

    public function canMutate(Creator $creator, User $actor): bool
    {
        if ($actor->can('manage any vtual resource')) {
            return true;
        }

        $role = $this->roleFor($creator, $actor);

        return $role !== null && $role->canMutate();
    }

    public function canManageMembers(Creator $creator, User $actor): bool
    {
        if ($actor->can('manage any vtual resource')) {
            return true;
        }

        $role = $this->roleFor($creator, $actor);

        return $role !== null && $role->canManageMembers();
    }

    public function assertCanView(Creator $creator, User $actor): void
    {
        if (! $this->canView($creator, $actor)) {
            throw new VtualException(__('api/vtual.policy.access_denied'), 403);
        }
    }

    public function assertCanMutate(Creator $creator, User $actor): void
    {
        if (! $this->canMutate($creator, $actor)) {
            throw new VtualException(__('api/vtual.policy.mutate_denied'), 403);
        }
    }

    public function assertCanManageMembers(Creator $creator, User $actor): void
    {
        if (! $this->canManageMembers($creator, $actor)) {
            throw new VtualException(__('api/vtual.policy.members_denied'), 403);
        }
    }
}
