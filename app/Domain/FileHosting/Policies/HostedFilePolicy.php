<?php

namespace App\Domain\FileHosting\Policies;

use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\AccessService;
use App\Domain\FileHosting\Services\WorkspaceAccessService;
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
        return $this->perm($actor, 'view file hosting file');
    }

    public function view(User $actor, HostedFile $file): Response
    {
        if (! $actor->can('view file hosting file')) {
            return $this->deny(__('api/file-hosting.policy.denied'));
        }

        if ($file->workspace_id !== null) {
            $workspace = $file->workspace ?? $this->workspaceAccess->findOrFail($file->workspace_id);

            return $this->workspaceAccess->canView($workspace, $actor)
                ? $this->allow()
                : $this->deny(__('api/file-hosting.workspace.access_denied'));
        }

        return $this->access->canViewOwner($actor, $file->owner)
            ? $this->allow()
            : $this->deny(__('api/file-hosting.policy.hierarchy_view'));
    }

    public function create(User $actor): Response
    {
        return $this->perm($actor, 'create file hosting file');
    }

    public function update(User $actor, HostedFile $file): Response
    {
        if (! $actor->can('edit file hosting file')) {
            return $this->deny(__('api/file-hosting.policy.denied'));
        }

        if ($file->workspace_id !== null) {
            $workspace = $file->workspace ?? $this->workspaceAccess->findOrFail($file->workspace_id);

            return $this->workspaceAccess->canMutate($workspace, $actor)
                ? $this->allow()
                : $this->deny(__('api/file-hosting.workspace.mutate_denied'));
        }

        return $this->access->canMutateOwner($actor, $file->owner)
            ? $this->allow()
            : $this->deny(__('api/file-hosting.policy.hierarchy_mutate'));
    }

    public function delete(User $actor, HostedFile $file): Response
    {
        if (! $actor->can('delete file hosting file')) {
            return $this->deny(__('api/file-hosting.policy.denied'));
        }

        if ($file->workspace_id !== null) {
            $workspace = $file->workspace ?? $this->workspaceAccess->findOrFail($file->workspace_id);

            return $this->workspaceAccess->canMutate($workspace, $actor)
                ? $this->allow()
                : $this->deny(__('api/file-hosting.workspace.mutate_denied'));
        }

        return $this->access->canMutateOwner($actor, $file->owner)
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
