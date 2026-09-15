<?php

namespace App\Domain\Archivd\Services;

use App\Domain\Archivd\Enums\WorkspaceKind;
use App\Domain\Archivd\Enums\WorkspaceMemberStatus;
use App\Domain\Archivd\Enums\WorkspaceRole;
use App\Domain\Archivd\Enums\WorkspaceStatus;
use App\Domain\Archivd\Exceptions\ArchivdException;
use App\Domain\Archivd\Models\Folder;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Models\Workspace;
use App\Domain\Archivd\Models\WorkspaceMember;
use App\Domain\Identity\Models\User;
use App\Domain\Service\Services\ServiceEntitlementService;
use App\Domain\Service\Support\ArchivdCatalog;
use App\Domain\Service\Support\MicrostockCatalog;
use Illuminate\Http\Request;

/**
 * Workspace membership + paid collab gate (separate from platform H-RBAC).
 */
class WorkspaceAccessService
{
    public function __construct(
        private readonly AccessService $access,
        private readonly ServiceEntitlementService $entitlements,
        private readonly QuotaService $quota,
    ) {}

    /**
     * First active owned workspace (legacy helper; multi-own uses countActiveOwned).
     */
    public function findActiveForOwner(string $ownerUserId): ?Workspace
    {
        return Workspace::query()
            ->where('owner_user_id', $ownerUserId)
            ->where('status', WorkspaceStatus::Active)
            ->orderBy('name')
            ->first();
    }

    public function countActiveOwned(string $ownerUserId): int
    {
        // Slot accounting counts only user-created team workspaces; built-in
        // system workspaces (microstock, …) are slot-exempt by design.
        return Workspace::query()
            ->where('owner_user_id', $ownerUserId)
            ->where('kind', WorkspaceKind::Team)
            ->where('status', WorkspaceStatus::Active)
            ->count();
    }

    /**
     * Count the owner's active Microstock system workspaces (standalone slots).
     */
    public function countActiveOwnedMicrostock(string $ownerUserId): int
    {
        return Workspace::query()
            ->where('owner_user_id', $ownerUserId)
            ->where('kind', WorkspaceKind::Microstock)
            ->where('status', WorkspaceStatus::Active)
            ->count();
    }

    /**
     * Standalone Microstock workspace slot limit: base (1) once the free
     * registration is active, plus compounding slot-addon quantity. 0 when the
     * user never activated Microstock. Does NOT overlap with Archivd team slots.
     */
    public function microstockSlotLimit(string $ownerUserId): int
    {
        $base = $this->entitlements->hasActiveMicrostockWorkspaceSlot($ownerUserId)
            ? max(0, (int) MicrostockCatalog::BASE_WORKSPACE_SLOTS)
            : 0;

        return $base + $this->entitlements->microstockWorkspaceSlotExtraQuantity($ownerUserId);
    }

    public function canProvisionMicrostockWorkspace(string $ownerUserId): bool
    {
        return $this->countActiveOwnedMicrostock($ownerUserId) < $this->microstockSlotLimit($ownerUserId);
    }

    /**
     * Gate Microstock system-workspace provisioning by standalone slot limit.
     */
    public function assertCanProvisionMicrostockWorkspace(string $ownerUserId): void
    {
        $limit = $this->microstockSlotLimit($ownerUserId);

        if ($limit <= 0) {
            throw new ArchivdException(__('api/microstock.workspace_not_activated'), 422);
        }

        $used = $this->countActiveOwnedMicrostock($ownerUserId);

        if ($used >= $limit) {
            throw new ArchivdException(
                __('api/microstock.slot_limit', ['used' => $used, 'limit' => $limit]),
                422,
            );
        }
    }

    /**
     * owned_limit = (paid storage ? base : 0) + sum(workspace-slot addon qty).
     * Slot-only entitlement without paid storage still yields limit from extras only
     * (create still blocked by assertOwnerEligibleForCollab which requires paid storage).
     */
    public function ownedSlotLimit(string $ownerUserId): int
    {
        $base = 0;
        $state = $this->quota->storageStateFor($ownerUserId);
        if ($state === 'paid' || $state === 'grace') {
            $base = max(0, (int) config(
                'archivd.base_owned_workspace_slots',
                ArchivdCatalog::BASE_OWNED_WORKSPACE_SLOTS,
            ));
        }

        return $base + $this->entitlements->workspaceSlotExtraQuantity($ownerUserId);
    }

    public function canCreateOwnedWorkspace(string $ownerUserId): bool
    {
        if ($this->quota->storageStateFor($ownerUserId) !== 'paid') {
            return false;
        }

        return $this->countActiveOwned($ownerUserId) < $this->ownedSlotLimit($ownerUserId);
    }

    public function assertCanCreateOwnedWorkspace(string $ownerUserId): void
    {
        $this->assertOwnerEligibleForCollab($ownerUserId);

        $used = $this->countActiveOwned($ownerUserId);
        $limit = $this->ownedSlotLimit($ownerUserId);

        if ($used >= $limit) {
            throw new ArchivdException(
                __('api/archivd.workspace.slot_limit', [
                    'used' => $used,
                    'limit' => $limit,
                ]),
                422,
            );
        }
    }

    public function findOrFail(string $workspaceId): Workspace
    {
        $workspace = Workspace::query()->find($workspaceId);

        if ($workspace === null) {
            throw new ArchivdException(__('api/archivd.workspace.not_found'), 404);
        }

        return $workspace;
    }

    public function activeMember(Workspace $workspace, string $userId): ?WorkspaceMember
    {
        return WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $userId)
            ->where('status', WorkspaceMemberStatus::Active)
            ->first();
    }

    public function roleFor(Workspace $workspace, User $actor): ?WorkspaceRole
    {
        if ($workspace->owner_user_id === $actor->id) {
            return WorkspaceRole::Owner;
        }

        $member = $this->activeMember($workspace, $actor->id);

        return $member?->role;
    }

    public const REQUEST_ROLES = 'archivd.workspace.roles';

    /**
     * @param  iterable<mixed>  $workspaces
     */
    public function primeRequestLookup(Request $request, iterable $workspaces, User $actor): void
    {
        $items = [];
        foreach ($workspaces as $workspace) {
            if ($workspace instanceof Workspace) {
                $items[(string) $workspace->id] = $workspace;
            }
        }

        if ($items === []) {
            return;
        }

        /** @var array<string, WorkspaceRole|null> $roles */
        $roles = $request->attributes->get(self::REQUEST_ROLES, []);
        $new = array_diff_key($items, $roles);

        if ($new === []) {
            return;
        }

        foreach ($new as $id => $workspace) {
            $roles[$id] = $workspace->owner_user_id === $actor->id ? WorkspaceRole::Owner : null;
        }

        $memberIds = [];
        foreach ($new as $id => $workspace) {
            if ($roles[$id] === null) {
                $memberIds[] = $id;
            }
        }

        if ($memberIds !== []) {
            $members = WorkspaceMember::query()
                ->where('user_id', $actor->id)
                ->where('status', WorkspaceMemberStatus::Active)
                ->whereIn('workspace_id', $memberIds)
                ->get(['workspace_id', 'role']);

            foreach ($members as $member) {
                $roles[(string) $member->workspace_id] = $member->role;
            }
        }

        $request->attributes->set(self::REQUEST_ROLES, $roles);
    }

    public function roleForRequest(Workspace $workspace, User $actor, Request $request): ?WorkspaceRole
    {
        /** @var array<string, WorkspaceRole|null> $roles */
        $roles = $request->attributes->get(self::REQUEST_ROLES, []);
        $id = (string) $workspace->id;

        if (array_key_exists($id, $roles)) {
            return $roles[$id];
        }

        return $this->roleFor($workspace, $actor);
    }

    public function canView(Workspace $workspace, User $actor): bool
    {
        if ($this->access->canViewOwner($actor, $workspace->owner)) {
            // Staff hierarchy may view owner's tree including workspace
            if ($actor->id !== $workspace->owner_user_id
                && ($actor->can('view any archivd resource') || $actor->can('manage any archivd resource'))
            ) {
                return true;
            }
        }

        return $this->roleFor($workspace, $actor) !== null;
    }

    public function canMutate(Workspace $workspace, User $actor): bool
    {
        if ($actor->id !== $workspace->owner_user_id
            && $actor->can('manage any archivd resource')
            && $this->access->canMutateOwner($actor, $workspace->owner)
        ) {
            return true;
        }

        $role = $this->roleFor($workspace, $actor);

        return $role?->canMutate() ?? false;
    }

    public function canManageMembers(Workspace $workspace, User $actor): bool
    {
        $role = $this->roleFor($workspace, $actor);

        return $role?->canManageMembers() ?? false;
    }

    public function assertCanView(Workspace $workspace, User $actor): void
    {
        if (! $workspace->isActive() && $this->roleFor($workspace, $actor) !== WorkspaceRole::Owner) {
            throw new ArchivdException(__('api/archivd.workspace.archived'), 422);
        }

        if (! $this->canView($workspace, $actor)) {
            throw new ArchivdException(__('api/archivd.workspace.access_denied'), 403);
        }
    }

    public function assertCanMutate(Workspace $workspace, User $actor): void
    {
        if (! $workspace->isActive()) {
            throw new ArchivdException(__('api/archivd.workspace.archived'), 422);
        }

        if (! $this->canMutate($workspace, $actor)) {
            throw new ArchivdException(__('api/archivd.workspace.mutate_denied'), 403);
        }
    }

    public function assertCanManageMembers(Workspace $workspace, User $actor): void
    {
        if (! $workspace->isActive()) {
            throw new ArchivdException(__('api/archivd.workspace.archived'), 422);
        }

        if (! $this->canManageMembers($workspace, $actor)) {
            throw new ArchivdException(__('api/archivd.workspace.members_denied'), 403);
        }
    }

    /**
     * Owner must have active paid Archivd (main or addon). Free-only blocked.
     */
    public function assertOwnerEligibleForCollab(string $ownerUserId): void
    {
        $state = $this->quota->storageStateFor($ownerUserId);

        if ($state === 'grace') {
            throw new ArchivdException(__('api/archivd.workspace.owner_in_grace'), 422);
        }

        if ($state !== 'paid') {
            throw new ArchivdException(__('api/archivd.workspace.requires_paid'), 422);
        }
    }

    public function assertResourceView(User $actor, HostedFile|Folder $resource): void
    {
        if ($resource->workspace_id === null) {
            $this->access->assertCanViewOwner($actor, $resource->owner);

            return;
        }

        $workspace = $resource->workspace ?? $this->findOrFail($resource->workspace_id);
        $this->assertCanView($workspace, $actor);
    }

    public function assertResourceMutate(User $actor, HostedFile|Folder $resource): void
    {
        if ($resource->workspace_id === null) {
            $this->access->assertCanMutateOwner($actor, $resource->owner);

            return;
        }

        $workspace = $resource->workspace ?? $this->findOrFail($resource->workspace_id);
        $this->assertCanMutate($workspace, $actor);
    }

    /**
     * Resolve storage owner id for a workspace (S3 + quota + DB user_id).
     */
    public function storageOwnerId(Workspace $workspace): string
    {
        return $workspace->owner_user_id;
    }
}
