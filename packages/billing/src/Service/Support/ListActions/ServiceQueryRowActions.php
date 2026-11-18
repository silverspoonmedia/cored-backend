<?php

namespace App\Domain\Service\Support\ListActions;

use App\Domain\Identity\Models\User;
use App\Domain\Service\Models\Service;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;

final class ServiceQueryRowActions implements QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof Service) {
            return [];
        }

        $id = (string) $model->id;
        $actions = [
            QueryRowAction::nav(
                key: 'view',
                label: __('ui.datatable.view'),
                icon: 'eye',
                variant: 'info',
                route: 'services.show',
                params: ['id' => $id],
            ),
        ];

        if ($actor !== null && $actor->can('manage service')) {
            $actions[] = QueryRowAction::nav(
                key: 'edit',
                label: __('ui.actions.edit'),
                icon: 'pencil',
                variant: 'primary',
                route: 'services.edit',
                params: ['id' => $id],
            );
            $actions[] = QueryRowAction::mutate(
                key: 'delete',
                label: __('ui.actions.delete'),
                icon: 'trash3',
                variant: 'danger',
                method: 'DELETE',
                path: 'services/'.$id,
                confirm: [
                    'title' => __('ui.datatable.delete'),
                    'text' => __('list_actions.confirm.delete_service'),
                ],
            );
        }

        return $actions;
    }
}
