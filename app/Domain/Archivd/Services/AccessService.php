<?php

namespace App\Domain\Archivd\Services;

use App\Domain\Archivd\Exceptions\ArchivdException;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\Rbac\RoleService;

/**
 * Resolves which owner's tree an actor may browse, and hierarchy gates for cross-user ops.
 */
class AccessService
{
    public function __construct(
        private readonly RoleService $roles,
    ) {}

    /**
     * Owner user id whose tree the actor is browsing.
     * Default: actor themselves. Admin may pass another user when allowed.
     */
    public function resolveOwnerId(User $actor, ?string $requestedUserId): string
    {
        if ($requestedUserId === null || $requestedUserId === '' || $requestedUserId === $actor->id) {
            return $actor->id;
        }

        if (! $actor->can('view any archivd resource') && ! $actor->can('manage any archivd resource')) {
            throw new ArchivdException(__('api/archivd.policy.cross_user_denied'), 403);
        }

        $owner = User::query()->find($requestedUserId);

        if ($owner === null) {
            throw new ArchivdException(__('api/archivd.owner_not_found'), 404);
        }

        if (! $this->roles->canActorViewTarget($actor, $owner)) {
            throw new ArchivdException(__('api/archivd.policy.hierarchy_view'), 403);
        }

        return $owner->id;
    }

    public function canViewOwner(User $actor, User $owner): bool
    {
        if ($actor->is($owner)) {
            return true;
        }

        if (! $actor->can('view any archivd resource') && ! $actor->can('manage any archivd resource')) {
            return false;
        }

        return $this->roles->canActorViewTarget($actor, $owner);
    }

    public function canMutateOwner(User $actor, User $owner): bool
    {
        if ($actor->is($owner)) {
            return true;
        }

        if (! $actor->can('manage any archivd resource')) {
            return false;
        }

        return $this->roles->allTargetRolesAboveActor($actor, $owner);
    }

    public function assertCanViewOwner(User $actor, User $owner): void
    {
        if (! $this->canViewOwner($actor, $owner)) {
            throw new ArchivdException(__('api/archivd.policy.hierarchy_view'), 403);
        }
    }

    public function assertCanMutateOwner(User $actor, User $owner): void
    {
        if (! $this->canMutateOwner($actor, $owner)) {
            throw new ArchivdException(__('api/archivd.policy.hierarchy_mutate'), 403);
        }
    }
}
