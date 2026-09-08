<?php

namespace App\Domain\Vtual\Services;


use Illuminate\Support\Facades\DB;
use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\CreatorMemberRole;
use App\Domain\Vtual\Enums\ManagementMemberRole;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\CreatorMember;
use App\Domain\Vtual\Models\ManagementMember;

class CreatorAccessService
{
    public function findOrFail(string $creatorId): Creator
    {
        return Creator::query()->findOrFail($creatorId);
    }

    /**
     * Membership row only — does not treat staff as Owner.
     */
    public function memberRoleFor(Creator $creator, User $actor): ?CreatorMemberRole
    {
        /** @var CreatorMember|null $member */
        $member = CreatorMember::query()
            ->where('creator_id', $creator->id)
            ->where('user_id', $actor->id)
            ->first();

        return $member?->role;
    }

    /**
     * Management role over this creator via an ACTIVE assignment
     * (vtual_management_creators, left_at null) plus membership in that
     * management (members are removed physically, so any row is live).
     */
    public function managementRoleForCreator(Creator $creator, User $actor): ?ManagementMemberRole
    {
        $managementIds = DB::table('vtual_management_creators')
            ->where('creator_id', $creator->id)
            ->whereNull('left_at')
            ->pluck('management_id');

        if ($managementIds->isEmpty()) {
            return null;
        }

        /** @var ManagementMember|null $member */
        $member = ManagementMember::query()
            ->whereIn('management_id', $managementIds)
            ->where('user_id', $actor->id)
            ->first();

        return $member?->role;
    }

    public function roleFor(Creator $creator, User $actor): ?CreatorMemberRole
    {
        if ($actor->can('manage any vtual resource')) {
            return CreatorMemberRole::Owner;
        }

        return $this->memberRoleFor($creator, $actor);
    }

    public function canView(Creator $creator, User $actor): bool
    {
        if ($actor->can('view any vtual resource') || $actor->can('manage any vtual resource')) {
            return true;
        }

        $role = $this->roleFor($creator, $actor);
        if ($role !== null && $role->canView()) {
            return true;
        }

        // Active management assignment: any member role may view the linked
        // creator profile (Owner/Admin mutate below, Staff view-only).
        return $this->managementRoleForCreator($creator, $actor) !== null;
    }

    public function canMutate(Creator $creator, User $actor): bool
    {
        if ($actor->can('manage any vtual resource')) {
            return true;
        }

        $role = $this->roleFor($creator, $actor);
        if ($role !== null && $role->canMutate()) {
            return true;
        }

        // Management Owner/Admin may edit linked creator profiles; Staff may not.
        $managementRole = $this->managementRoleForCreator($creator, $actor);

        return $managementRole === ManagementMemberRole::Owner
            || $managementRole === ManagementMemberRole::Admin;
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
