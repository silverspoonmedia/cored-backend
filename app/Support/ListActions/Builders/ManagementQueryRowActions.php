<?php

namespace App\Support\ListActions\Builders;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Models\Management;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;

final class ManagementQueryRowActions implements QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof Management || $actor === null || ! $actor->can('manage any vtual resource')) {
            return [];
        }

        return [
            QueryRowAction::nav(
                key: 'edit',
                label: __('ui.actions.edit'),
                icon: 'pencil',
                variant: 'secondary',
                route: 'vtual.managements.edit',
                params: ['id' => (string) $model->id],
            ),
            QueryRowAction::mutate(
                key: 'archive',
                label: __('ui.actions.delete'),
                icon: 'trash3',
                variant: 'danger',
                method: 'DELETE',
                path: 'vtual/managements/'.$model->id,
                confirm: [
                    'title' => __('ui.datatable.delete'),
                    'text' => __('list_actions.confirm.archive_management'),
                ],
            ),
        ];
    }
}
