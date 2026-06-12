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
                label: __('ui.datatable.view'),
                method: 'GET',
                url: route('playground.users.show', $model),
                style: 'info',
                icon: 'fas fa-eye',
            ),
            new ActionButton(
                key: 'edit',
                label: __('ui.datatable.edit'),
                method: 'PATCH',
                url: route('playground.users.update', $model),
                style: 'primary',
                icon: 'fas fa-edit',
            ),
            new ActionButton(
                key: 'delete',
                label: __('ui.datatable.delete'),
                method: 'DELETE',
                url: route('playground.users.destroy', $model),
                style: 'danger',
                icon: 'fas fa-trash',
                confirm: __('ui.datatable.confirm.delete_playground_user'),
            ),
        ];
    }
}
