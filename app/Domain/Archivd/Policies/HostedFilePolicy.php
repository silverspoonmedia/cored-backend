<?php

namespace App\Domain\Archivd\Policies;

use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Services\AccessService;
use App\Domain\Archivd\Services\WorkspaceAccessService;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class HostedFilePolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly AccessService $access,
        private readonly WorkspaceAccessService $workspaceAccess,
    ) {}

    public function viewAny(User $actor): Response
    {
        return $this->perm($actor, 'view archivd file');
    }

    public function view(User $actor, HostedFile $file): Response
    {
        if (! $actor->can('view archivd file')) {
            return $this->deny(__('api/archivd.policy.denied'));
        }

        if ($file->workspace_id !== null) {
            $workspace = $file->workspace ?? $this->workspaceAccess->findOrFail($file->workspace_id);

            return $this->workspaceAccess->canView($workspace, $actor)
                ? $this->allow()
                : $this->deny(__('api/archivd.workspace.access_denied'));
        }

        return $this->access->canViewOwner($actor, $file->owner)
            ? $this->allow()
            : $this->deny(__('api/archivd.policy.hierarchy_view'));
    }

    public function create(User $actor): Response
    {
        return $this->perm($actor, 'create archivd file');
    }

    public function update(User $actor, HostedFile $file): Response
    {
        if (! $actor->can('edit archivd file')) {
            return $this->deny(__('api/archivd.policy.denied'));
        }

        if ($file->workspace_id !== null) {
            $workspace = $file->workspace ?? $this->workspaceAccess->findOrFail($file->workspace_id);

            return $this->workspaceAccess->canMutate($workspace, $actor)
                ? $this->allow()
                : $this->deny(__('api/archivd.workspace.mutate_denied'));
        }

        return $this->access->canMutateOwner($actor, $file->owner)
            ? $this->allow()
            : $this->deny(__('api/archivd.policy.hierarchy_mutate'));
    }

    public function delete(User $actor, HostedFile $file): Response
    {
        if (! $actor->can('delete archivd file')) {
            return $this->deny(__('api/archivd.policy.denied'));
        }

        if ($file->workspace_id !== null) {
            $workspace = $file->workspace ?? $this->workspaceAccess->findOrFail($file->workspace_id);

            return $this->workspaceAccess->canMutate($workspace, $actor)
                ? $this->allow()
                : $this->deny(__('api/archivd.workspace.mutate_denied'));
        }

        return $this->access->canMutateOwner($actor, $file->owner)
            ? $this->allow()
            : $this->deny(__('api/archivd.policy.hierarchy_mutate'));
    }

    private function perm(User $actor, string $permission): Response
    {
        return $actor->can($permission)
            ? $this->allow()
            : $this->deny(__('api/archivd.policy.denied'));
    }
}
