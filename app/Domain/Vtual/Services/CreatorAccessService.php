<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\CreatorMemberRole;
use App\Domain\Vtual\Enums\ManagementMemberRole;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\CreatorMember;
use App\Domain\Vtual\Models\ManagementMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreatorAccessService
{
    public const REQUEST_ROLES = 'vtual.creator.roles';

    public const REQUEST_MGMT_ROLES = 'vtual.creator.mgmt_roles';

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

    /**
     * @param  iterable<mixed>  $creators
     */
    public function primeRequestLookup(Request $request, iterable $creators, User $actor): void
    {
        $ids = [];
        foreach ($creators as $creator) {
            if ($creator instanceof Creator) {
                $ids[(string) $creator->id] = true;
            }
        }

        if ($ids === []) {
            return;
        }

        /** @var array<string, CreatorMemberRole|null> $roles */
        $roles = $request->attributes->get(self::REQUEST_ROLES, []);
        /** @var array<string, ManagementMemberRole|null> $mgmt */
        $mgmt = $request->attributes->get(self::REQUEST_MGMT_ROLES, []);
        $newIds = array_diff_key($ids, $roles);

        if ($newIds === []) {
            return;
        }

        $idList = array_keys($newIds);
        $isStaff = $actor->can('manage any vtual resource');

        foreach ($idList as $id) {
            $roles[$id] = $isStaff ? CreatorMemberRole::Owner : null;
            $mgmt[$id] = $mgmt[$id] ?? null;
        }

        if (! $isStaff) {
            $members = CreatorMember::query()
                ->where('user_id', $actor->id)
                ->whereIn('creator_id', $idList)
                ->get(['creator_id', 'role']);

            foreach ($members as $member) {
                $roles[(string) $member->creator_id] = $member->role;
            }
        }

        $assignments = DB::table('vtual_management_creators')
            ->whereIn('creator_id', $idList)
            ->whereNull('left_at')
            ->get(['creator_id', 'management_id']);

        $managementIds = $assignments->pluck('management_id')->unique()->filter()->values();
        $memberships = $managementIds->isEmpty()
            ? collect()
            : ManagementMember::query()
                ->where('user_id', $actor->id)
                ->whereIn('management_id', $managementIds)
                ->get()
                ->keyBy(fn (ManagementMember $row): string => (string) $row->management_id);

        foreach ($assignments->groupBy('creator_id') as $creatorId => $rows) {
            foreach ($rows as $row) {
                $member = $memberships->get((string) $row->management_id);
                if ($member instanceof ManagementMember) {
                    $mgmt[(string) $creatorId] = $member->role;
                    break;
                }
            }
        }

        $request->attributes->set(self::REQUEST_ROLES, $roles);
        $request->attributes->set(self::REQUEST_MGMT_ROLES, $mgmt);
    }

    public function roleForRequest(Creator $creator, User $actor, Request $request): ?CreatorMemberRole
    {
        /** @var array<string, CreatorMemberRole|null> $roles */
        $roles = $request->attributes->get(self::REQUEST_ROLES, []);
        $id = (string) $creator->id;

        if (array_key_exists($id, $roles)) {
            return $roles[$id];
        }

        return $this->roleFor($creator, $actor);
    }

    public function managementRoleForCreatorRequest(Creator $creator, User $actor, Request $request): ?ManagementMemberRole
    {
        /** @var array<string, ManagementMemberRole|null> $mgmt */
        $mgmt = $request->attributes->get(self::REQUEST_MGMT_ROLES, []);
        $id = (string) $creator->id;

        if (array_key_exists($id, $mgmt)) {
            return $mgmt[$id];
        }

        return $this->managementRoleForCreator($creator, $actor);
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
