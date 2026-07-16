<?php

namespace App\Domain\Product\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Product\Models\Item;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class ItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $actor): Response
    {
        return $this->check($actor, 'view product item');
    }

    public function view(User $actor, Item $item): Response
    {
        return $this->check($actor, 'view product item');
    }

    public function create(User $actor): Response
    {
        return $this->check($actor, 'create product item');
    }

    public function update(User $actor, Item $item): Response
    {
        return $this->check($actor, 'edit product item');
    }

    public function delete(User $actor, Item $item): Response
    {
        return $this->check($actor, 'delete product item');
    }

    private function check(User $actor, string $permission): Response
    {
        return $actor->can($permission)
            ? $this->allow()
            : $this->deny(__('api/product-items.policy.denied'));
    }
}
