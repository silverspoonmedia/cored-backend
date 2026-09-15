<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ManagementMemberRole;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\ManagementMember;
use Illuminate\Http\Request;

class ManagementAccessService
{
    public const REQUEST_ROLES = 'vtual.management.roles';

    public const REQUEST_MEMBER_ROLES = 'vtual.management.member_roles';

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

    /**
     * @param  iterable<mixed>  $managements
     */
    public function primeRequestLookup(Request $request, iterable $managements, User $actor): void
    {
        $ids = [];
        foreach ($managements as $management) {
            if ($management instanceof Management) {
                $ids[(string) $management->id] = true;
            }
        }

        if ($ids === []) {
            return;
        }

        /** @var array<string, ManagementMemberRole|null> $roles */
        $roles = $request->attributes->get(self::REQUEST_ROLES, []);
        /** @var array<string, ManagementMemberRole|null> $memberRoles */
        $memberRoles = $request->attributes->get(self::REQUEST_MEMBER_ROLES, []);
        $newIds = array_diff_key($ids, $roles);

        if ($newIds === []) {
            return;
        }

        $idList = array_keys($newIds);
        $isStaff = $actor->can('manage any vtual resource');

        foreach ($idList as $id) {
            $roles[$id] = $isStaff ? ManagementMemberRole::Owner : null;
            $memberRoles[$id] = $memberRoles[$id] ?? null;
        }

        $members = ManagementMember::query()
            ->where('user_id', $actor->id)
            ->whereIn('management_id', $idList)
            ->get(['management_id', 'role']);

        foreach ($members as $member) {
            $memberRoles[(string) $member->management_id] = $member->role;
            if (! $isStaff) {
                $roles[(string) $member->management_id] = $member->role;
            }
        }

        $request->attributes->set(self::REQUEST_ROLES, $roles);
        $request->attributes->set(self::REQUEST_MEMBER_ROLES, $memberRoles);
    }

    public function roleForRequest(Management $management, User $actor, Request $request): ?ManagementMemberRole
    {
        /** @var array<string, ManagementMemberRole|null> $roles */
        $roles = $request->attributes->get(self::REQUEST_ROLES, []);
        $id = (string) $management->id;

        if (array_key_exists($id, $roles)) {
            return $roles[$id];
        }

        return $this->roleFor($management, $actor);
    }

    public function memberRoleForRequest(Management $management, User $actor, Request $request): ?ManagementMemberRole
    {
        /** @var array<string, ManagementMemberRole|null> $roles */
        $roles = $request->attributes->get(self::REQUEST_MEMBER_ROLES, []);
        $id = (string) $management->id;

        if (array_key_exists($id, $roles)) {
            return $roles[$id];
        }

        return $this->lookupMemberRole($management, $actor);
    }

    private function lookupMemberRole(Management $management, User $actor): ?ManagementMemberRole
    {
        /** @var ManagementMember|null $member */
        $member = ManagementMember::query()
            ->where('management_id', $management->id)
            ->where('user_id', $actor->id)
            ->first();

        return $member?->role;
    }

    private function roleForCurrentRequest(Management $management, User $actor): ?ManagementMemberRole
    {
        $request = request();

        if ($request instanceof Request) {
            return $this->roleForRequest($management, $actor, $request);
        }

        return $this->roleFor($management, $actor);
    }

    public function canView(Management $management, User $actor): bool
    {
        if ($actor->can('view any vtual resource') || $actor->can('manage any vtual resource')) {
            return true;
        }

        $role = $this->roleForCurrentRequest($management, $actor);

        return $role !== null && $role->canView();
    }

    public function canMutate(Management $management, User $actor): bool
    {
        if ($actor->can('manage any vtual resource')) {
            return true;
        }

        $role = $this->roleForCurrentRequest($management, $actor);

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
        $request = request();
        $claims = app(ManagementService::class);

        if ($actor->can('manage any vtual resource')) {
            $claimable = $request instanceof Request
                ? $claims->isClaimableForRequest($management, $request)
                : $claims->isClaimable($management);

            if ($claimable) {
                return true;
            }
        }

        $role = $request instanceof Request
            ? $this->memberRoleForRequest($management, $actor, $request)
            : $this->lookupMemberRole($management, $actor);

        return $role === ManagementMemberRole::Owner || $role === ManagementMemberRole::Admin;
    }

    public function canAssignCreators(Management $management, User $actor): bool
    {
        if ($actor->can('manage any vtual resource')) {
            return true;
        }

        $role = $this->roleForCurrentRequest($management, $actor);

        return $role !== null && $role->canAssignCreators();
    }

    public function canManageMembers(Management $management, User $actor): bool
    {
        if ($actor->can('manage any vtual resource')) {
            return true;
        }

        $role = $this->roleForCurrentRequest($management, $actor);

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
