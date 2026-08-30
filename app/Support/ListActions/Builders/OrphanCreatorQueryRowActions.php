<?php

namespace App\Support\ListActions\Builders;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Models\Creator;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;

final class OrphanCreatorQueryRowActions implements QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof Creator || $actor === null || ! $actor->can('manage any vtual resource')) {
            return [];
        }

        return [
            QueryRowAction::nav(
                key: 'edit',
                label: __('ui.actions.edit'),
                icon: 'pencil',
                variant: 'secondary',
                route: 'vtual.orphans.edit',
                params: ['id' => (string) $model->id],
            ),
            QueryRowAction::mutate(
                key: 'delete',
                label: __('ui.actions.delete'),
                icon: 'trash3',
                variant: 'danger',
                method: 'DELETE',
                path: 'vtual/creators/'.$model->id,
                confirm: [
                    'title' => __('ui.datatable.delete'),
                    'text' => __('web/vtual-orphans.datatable.confirm_delete'),
                ],
            ),
        ];
    }
}
