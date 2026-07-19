<?php

namespace App\Domain\FileHosting\Policies;

use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Services\AccessService;
use App\Domain\FileHosting\Services\WorkspaceAccessService;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class FolderPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly AccessService $access,
        private readonly WorkspaceAccessService $workspaceAccess,
    ) {}

    public function viewAny(User $actor): Response
    {
        return $this->perm($actor, 'view file hosting folder');
    }

    public function view(User $actor, Folder $folder): Response
    {
        if (! $actor->can('view file hosting folder')) {
            return $this->deny(__('api/file-hosting.policy.denied'));
        }

        if ($folder->workspace_id !== null) {
            $workspace = $folder->workspace ?? $this->workspaceAccess->findOrFail($folder->workspace_id);

            return $this->workspaceAccess->canView($workspace, $actor)
                ? $this->allow()
                : $this->deny(__('api/file-hosting.workspace.access_denied'));
        }

        return $this->access->canViewOwner($actor, $folder->owner)
            ? $this->allow()
            : $this->deny(__('api/file-hosting.policy.hierarchy_view'));
    }

    public function create(User $actor): Response
    {
        return $this->perm($actor, 'create file hosting folder');
    }

    public function update(User $actor, Folder $folder): Response
    {
        if (! $actor->can('edit file hosting folder')) {
            return $this->deny(__('api/file-hosting.policy.denied'));
        }

        if ($folder->workspace_id !== null) {
            $workspace = $folder->workspace ?? $this->workspaceAccess->findOrFail($folder->workspace_id);

            return $this->workspaceAccess->canMutate($workspace, $actor)
                ? $this->allow()
                : $this->deny(__('api/file-hosting.workspace.mutate_denied'));
        }

        return $this->access->canMutateOwner($actor, $folder->owner)
            ? $this->allow()
            : $this->deny(__('api/file-hosting.policy.hierarchy_mutate'));
    }

    public function delete(User $actor, Folder $folder): Response
    {
        if (! $actor->can('delete file hosting folder')) {
            return $this->deny(__('api/file-hosting.policy.denied'));
        }

        if ($folder->workspace_id !== null) {
            $workspace = $folder->workspace ?? $this->workspaceAccess->findOrFail($folder->workspace_id);

            return $this->workspaceAccess->canMutate($workspace, $actor)
                ? $this->allow()
                : $this->deny(__('api/file-hosting.workspace.mutate_denied'));
        }

        return $this->access->canMutateOwner($actor, $folder->owner)
            ? $this->allow()
            : $this->deny(__('api/file-hosting.policy.hierarchy_mutate'));
    }

    private function perm(User $actor, string $permission): Response
    {
        return $actor->can($permission)
            ? $this->allow()
            : $this->deny(__('api/file-hosting.policy.denied'));
    }
}
