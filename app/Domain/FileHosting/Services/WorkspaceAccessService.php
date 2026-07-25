<?php

namespace App\Domain\FileHosting\Services;

use App\Domain\FileHosting\Enums\WorkspaceMemberStatus;
use App\Domain\FileHosting\Enums\WorkspaceRole;
use App\Domain\FileHosting\Enums\WorkspaceStatus;
use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Models\Workspace;
use App\Domain\FileHosting\Models\WorkspaceMember;
use App\Domain\Identity\Models\User;
use App\Domain\Service\Services\ServiceEntitlementService;
use App\Domain\Service\Support\FileHostingCatalog;

/**
 * Workspace membership + paid collab gate (separate from platform H-RBAC).
 */
class WorkspaceAccessService
{
    public function __construct(
        private readonly AccessService $access,
        private readonly ServiceEntitlementService $entitlements,
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
        return Workspace::query()
            ->where('owner_user_id', $ownerUserId)
            ->where('status', WorkspaceStatus::Active)
            ->count();
    }

    /**
     * owned_limit = (paid storage ? base : 0) + sum(workspace-slot addon qty).
     * Slot-only entitlement without paid storage still yields limit from extras only
     * (create still blocked by assertOwnerEligibleForCollab which requires paid storage).
     */
    public function ownedSlotLimit(string $ownerUserId): int
    {
        $base = 0;
        if ($this->entitlements->hasActivePaidFileHosting($ownerUserId)
            || $this->entitlements->hasGraceFileHosting($ownerUserId)
        ) {
            $base = max(0, (int) config(
                'file-hosting.base_owned_workspace_slots',
                FileHostingCatalog::BASE_OWNED_WORKSPACE_SLOTS,
            ));
        }

        return $base + $this->entitlements->workspaceSlotExtraQuantity($ownerUserId);
    }

    public function canCreateOwnedWorkspace(string $ownerUserId): bool
    {
        if ($this->entitlements->hasGraceFileHosting($ownerUserId)) {
            return false;
        }

        if (! $this->entitlements->hasActivePaidFileHosting($ownerUserId)) {
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
            throw new FileHostingException(
                __('api/file-hosting.workspace.slot_limit', [
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
            throw new FileHostingException(__('api/file-hosting.workspace.not_found'), 404);
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

    public function canView(Workspace $workspace, User $actor): bool
    {
        if ($this->access->canViewOwner($actor, $workspace->owner)) {
            // Staff hierarchy may view owner's tree including workspace
            if ($actor->id !== $workspace->owner_user_id
                && ($actor->can('view any file hosting resource') || $actor->can('manage any file hosting resource'))
            ) {
                return true;
            }
        }

        return $this->roleFor($workspace, $actor) !== null;
    }

    public function canMutate(Workspace $workspace, User $actor): bool
    {
        if ($actor->id !== $workspace->owner_user_id
            && $actor->can('manage any file hosting resource')
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
            throw new FileHostingException(__('api/file-hosting.workspace.archived'), 422);
        }

        if (! $this->canView($workspace, $actor)) {
            throw new FileHostingException(__('api/file-hosting.workspace.access_denied'), 403);
        }
    }

    public function assertCanMutate(Workspace $workspace, User $actor): void
    {
        if (! $workspace->isActive()) {
            throw new FileHostingException(__('api/file-hosting.workspace.archived'), 422);
        }

        if (! $this->canMutate($workspace, $actor)) {
            throw new FileHostingException(__('api/file-hosting.workspace.mutate_denied'), 403);
        }
    }

    public function assertCanManageMembers(Workspace $workspace, User $actor): void
    {
        if (! $workspace->isActive()) {
            throw new FileHostingException(__('api/file-hosting.workspace.archived'), 422);
        }

        if (! $this->canManageMembers($workspace, $actor)) {
            throw new FileHostingException(__('api/file-hosting.workspace.members_denied'), 403);
        }
    }

    /**
     * Owner must have active paid FH (main or addon). Free-only blocked.
     */
    public function assertOwnerEligibleForCollab(string $ownerUserId): void
    {
        if ($this->entitlements->hasGraceFileHosting($ownerUserId)) {
            throw new FileHostingException(__('api/file-hosting.workspace.owner_in_grace'), 422);
        }

        if (! $this->entitlements->hasActivePaidFileHosting($ownerUserId)) {
            throw new FileHostingException(__('api/file-hosting.workspace.requires_paid'), 422);
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
