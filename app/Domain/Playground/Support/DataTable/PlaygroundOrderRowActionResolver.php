<?php

namespace App\Domain\Playground\Support\DataTable;

use App\Domain\Playground\Models\Order;
use App\Domain\Identity\Models\User;
use App\Support\DataTable\ActionButton;
use App\Support\DataTable\Contracts\RowActionResolverInterface;

final class PlaygroundOrderRowActionResolver implements RowActionResolverInterface
{
    /**
     * Playground CRUD has no RBAC; every row exposes the full action set.
     * Completed orders are frozen, so edit/delete are dropped for them (the API
     * also rejects those calls — this just hides the buttons).
     *
     * @return list<ActionButton>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof Order) {
            return [];
        }

        $actions = [
            new ActionButton(
                key: 'show',
                label: __('ui.datatable.view'),
                method: 'GET',
                url: route('playground.orders.show', $model),
                style: 'info',
                icon: 'fas fa-eye',
            ),
        ];

        if ($model->status !== 'completed') {
            $actions[] = new ActionButton(
                key: 'edit',
                label: __('ui.datatable.edit'),
                method: 'PUT',
                url: route('playground.orders.update', $model),
                style: 'primary',
                icon: 'fas fa-edit',
            );

            $actions[] = new ActionButton(
                key: 'delete',
                label: __('ui.datatable.delete'),
                method: 'DELETE',
                url: route('playground.orders.destroy', $model),
                style: 'danger',
                icon: 'fas fa-trash',
                confirm: __('ui.datatable.confirm.delete_playground_order'),
            );
        }

        return $actions;
    }
}
