<?php

namespace App\Support\DataTable\Resolvers;

use App\Models\Menu;
use App\Models\User;
use App\Support\DataTable\ActionButton;
use App\Support\DataTable\Contracts\RowActionResolverInterface;

final class MenuRowActionResolver implements RowActionResolverInterface
{
    /**
     * @return list<ActionButton>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof Menu || ! $actor instanceof User) {
            return [];
        }

        $actions = [];

        if ($actor->can('view menu')) {
            $actions[] = new ActionButton(
                key: 'show',
                label: __('ui.datatable.detail'),
                method: 'GET',
                url: route('api.v1.navigation.menus.show', $model),
                style: 'info',
                icon: 'fas fa-eye',
            );
        }

        if ($actor->can('manage menu')) {
            $actions[] = new ActionButton(
                key: 'edit',
                label: __('ui.datatable.edit'),
                method: 'PATCH',
                url: route('api.v1.navigation.menus.update', $model),
                style: 'primary',
                icon: 'fas fa-edit',
            );

            $actions[] = new ActionButton(
                key: 'delete',
                label: __('ui.datatable.delete'),
                method: 'DELETE',
                url: route('api.v1.navigation.menus.destroy', $model),
                style: 'danger',
                icon: 'fas fa-trash',
                confirm: __('ui.datatable.confirm.delete_menu'),
            );
        }

        return $actions;
    }
}
