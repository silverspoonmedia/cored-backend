<?php

namespace App\Domain\Inference\Support\ListActions;

use App\Domain\Identity\Models\User;
use App\Domain\Inference\Models\InferenceProvider;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;

final class InferenceProviderQueryRowActions implements QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof InferenceProvider) {
            return [];
        }

        $id = (string) $model->id;
        $actions = [
            QueryRowAction::nav(
                key: 'view',
                label: __('ui.datatable.view'),
                icon: 'eye',
                variant: 'secondary',
                route: 'inference.providers.show',
                params: ['id' => $id],
            ),
        ];

        if ($actor !== null && $actor->can('manage inference catalog')) {
            $actions[] = QueryRowAction::nav(
                key: 'edit',
                label: __('ui.actions.edit'),
                icon: 'pencil',
                variant: 'primary',
                route: 'inference.providers.edit',
                params: ['id' => $id],
            );
            $actions[] = QueryRowAction::mutate(
                key: 'delete',
                label: __('ui.actions.delete'),
                icon: 'trash3',
                variant: 'danger',
                method: 'DELETE',
                path: 'inference/providers/'.$id,
                confirm: [
                    'title' => __('ui.datatable.delete'),
                    'text' => __('list_actions.confirm.delete_inference'),
                ],
            );
        }

        return $actions;
    }
}
