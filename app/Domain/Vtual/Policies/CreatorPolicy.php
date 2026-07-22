<?php

namespace App\Domain\Vtual\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Services\CreatorAccessService;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class CreatorPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly CreatorAccessService $access,
    ) {}

    public function viewAny(User $actor): Response
    {
        return $this->perm($actor, 'view vtual creator');
    }

    public function view(User $actor, Creator $creator): Response
    {
        if (! $actor->can('view vtual creator')) {
            return $this->deny(__('api/vtual.policy.denied'));
        }

        return $this->access->canView($creator, $actor)
            ? $this->allow()
            : $this->deny(__('api/vtual.policy.access_denied'));
    }

    public function create(User $actor): Response
    {
        return $this->perm($actor, 'create vtual creator');
    }

    public function update(User $actor, Creator $creator): Response
    {
        if (! $actor->can('edit vtual creator')) {
            return $this->deny(__('api/vtual.policy.denied'));
        }

        return $this->access->canMutate($creator, $actor)
            ? $this->allow()
            : $this->deny(__('api/vtual.policy.mutate_denied'));
    }

    public function delete(User $actor, Creator $creator): Response
    {
        if (! $actor->can('delete vtual creator')) {
            return $this->deny(__('api/vtual.policy.denied'));
        }

        return $this->access->canManageMembers($creator, $actor)
            ? $this->allow()
            : $this->deny(__('api/vtual.policy.members_denied'));
    }

    private function perm(User $actor, string $permission): Response
    {
        return $actor->can($permission)
            ? $this->allow()
            : $this->deny(__('api/vtual.policy.denied'));
    }
}
