<?php

namespace App\Support\DataTable\Resolvers;

use App\Models\Menu;
use App\Models\User;
use App\Support\DataTable\ActionButton;
use App\Support\DataTable\Contracts\RowActionResolverInterface;

final class MenuTrashedRowActionResolver implements RowActionResolverInterface
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
                label: 'Detail',
                method: 'GET',
                url: route('api.v1.navigation.menus.show', $model),
                style: 'info',
                icon: 'fas fa-eye',
            );
        }

        if ($actor->can('restore menu')) {
            $actions[] = new ActionButton(
                key: 'restore',
                label: 'Restore',
                method: 'POST',
                url: route('api.v1.navigation.menus.restore', $model),
                style: 'success',
                icon: 'fas fa-undo',
                confirm: 'Restore this menu item and its trashed subtree?',
            );
        }

        return $actions;
    }
}
