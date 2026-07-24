<?php

namespace App\Domain\Vtual\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Services\CreatorAccessService;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class ChannelPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly CreatorAccessService $creatorAccess,
    ) {}

    public function viewAny(User $actor): Response
    {
        return $this->perm($actor, 'view vtual channel');
    }

    public function view(User $actor, Channel $channel): Response
    {
        if (! $actor->can('view vtual channel')) {
            return $this->deny(__('api/vtual.policy.denied'));
        }

        $creator = $channel->creator ?? $this->creatorAccess->findOrFail($channel->creator_id);

        return $this->creatorAccess->canView($creator, $actor)
            ? $this->allow()
            : $this->deny(__('api/vtual.policy.access_denied'));
    }

    public function create(User $actor): Response
    {
        return $this->perm($actor, 'create vtual channel');
    }

    private function perm(User $actor, string $permission): Response
    {
        return $actor->can($permission)
            ? $this->allow()
            : $this->deny(__('api/vtual.policy.denied'));
    }
}
