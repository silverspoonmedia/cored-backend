<?php

namespace App\Domain\Navigation\Support\DataTable;

use App\Domain\Navigation\Models\Menu;
use App\Domain\Identity\Models\User;
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
                label: __('ui.datatable.detail'),
                method: 'GET',
                url: route('api.v1.navigation.menus.show', $model),
                style: 'info',
                icon: 'fas fa-eye',
            );
        }

        if ($actor->can('restore menu')) {
            $actions[] = new ActionButton(
                key: 'restore',
                label: __('ui.datatable.restore'),
                method: 'POST',
                url: route('api.v1.navigation.menus.restore', $model),
                style: 'success',
                icon: 'fas fa-undo',
                confirm: __('ui.datatable.confirm.restore_menu'),
            );
        }

        return $actions;
    }
}
