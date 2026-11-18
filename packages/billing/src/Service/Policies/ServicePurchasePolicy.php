<?php

namespace App\Domain\Service\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Service\Models\ServicePurchase;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class ServicePurchasePolicy
{
    use HandlesAuthorization;

    public function view(User $actor, ServicePurchase $purchase): Response
    {
        if ($actor->can('view service') || $actor->can('view invoice')) {
            return $this->allow();
        }

        if ($purchase->user_id === $actor->id) {
            return $this->allow();
        }

        return $this->deny(__('api/services.policy.denied'));
    }

    public function updateQuantity(User $actor, ServicePurchase $purchase): Response
    {
        return $this->view($actor, $purchase);
    }
}
