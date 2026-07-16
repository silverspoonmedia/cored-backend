<?php

namespace App\Domain\Service\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Service\Models\Service;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class ServicePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $actor): Response
    {
        return $this->check($actor, 'view service');
    }

    public function view(User $actor, Service $service): Response
    {
        return $this->check($actor, 'view service');
    }

    public function create(User $actor): Response
    {
        return $this->check($actor, 'manage service');
    }

    public function update(User $actor, Service $service): Response
    {
        return $this->check($actor, 'manage service');
    }

    public function delete(User $actor, Service $service): Response
    {
        return $this->check($actor, 'manage service');
    }

    public function purchase(User $actor, Service $service): Response
    {
        if ($actor->can('purchase service') || $actor->can('initiate payment')) {
            return $this->allow();
        }

        return $this->deny(__('api/services.policy.denied'));
    }

    private function check(User $actor, string $permission): Response
    {
        return $actor->can($permission)
            ? $this->allow()
            : $this->deny(__('api/services.policy.denied'));
    }
}
