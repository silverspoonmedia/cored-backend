<?php

namespace App\Support\DataTable\Resolvers;

use App\Models\User;
use App\Support\DataTable\ActionButton;
use App\Support\DataTable\Contracts\RowActionResolverInterface;

final class PlaygroundUserRowActionResolver implements RowActionResolverInterface
{
    /**
     * Playground CRUD has no RBAC; every row exposes the full action set.
     *
     * @return list<ActionButton>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof User) {
            return [];
        }

        return [
            new ActionButton(
                key: 'show',
                label: 'View',
                method: 'GET',
                url: route('playground.users.show', $model),
                style: 'info',
                icon: 'fas fa-eye',
            ),
            new ActionButton(
                key: 'edit',
                label: 'Edit',
                method: 'PATCH',
                url: route('playground.users.update', $model),
                style: 'primary',
                icon: 'fas fa-edit',
            ),
            new ActionButton(
                key: 'delete',
                label: 'Delete',
                method: 'DELETE',
                url: route('playground.users.destroy', $model),
                style: 'danger',
                icon: 'fas fa-trash',
                confirm: 'Delete this user?',
            ),
        ];
    }
}
