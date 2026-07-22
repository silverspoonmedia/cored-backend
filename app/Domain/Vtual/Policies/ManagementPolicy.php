<?php

namespace App\Domain\Vtual\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Services\ManagementAccessService;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class ManagementPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly ManagementAccessService $access,
    ) {}

    public function viewAny(User $actor): Response
    {
        return $this->perm($actor, 'view vtual management');
    }

    public function view(User $actor, Management $management): Response
    {
        if (! $actor->can('view vtual management')) {
            return $this->deny(__('api/vtual.policy.denied'));
        }

        return $this->access->canView($management, $actor)
            ? $this->allow()
            : $this->deny(__('api/vtual.policy.access_denied'));
    }

    public function create(User $actor): Response
    {
        return $this->perm($actor, 'create vtual management');
    }

    public function update(User $actor, Management $management): Response
    {
        if (! $actor->can('edit vtual management')) {
            return $this->deny(__('api/vtual.policy.denied'));
        }

        return $this->access->canMutate($management, $actor)
            ? $this->allow()
            : $this->deny(__('api/vtual.policy.mutate_denied'));
    }

    private function perm(User $actor, string $permission): Response
    {
        return $actor->can($permission)
            ? $this->allow()
            : $this->deny(__('api/vtual.policy.denied'));
    }
}
