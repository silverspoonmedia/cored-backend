<?php

namespace App\Domain\Ai\Support\ListActions;

use App\Domain\Ai\Enums\AiConnectionStatus;
use App\Domain\Ai\Models\AiConnection;
use App\Domain\Identity\Models\User;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;

final class AiConnectionQueryRowActions implements QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof AiConnection || $actor === null || ! $actor->can('manage ai connections')) {
            return [];
        }

        $id = (string) $model->id;
        $actions = [
            QueryRowAction::mutate(
                key: 'test',
                label: __('list_actions.test'),
                icon: 'lightning',
                variant: 'success',
                method: 'POST',
                path: 'ai/connections/'.$id.'/test',
            ),
        ];

        if (! $model->is_default && $model->status === AiConnectionStatus::Active) {
            $actions[] = QueryRowAction::mutate(
                key: 'default',
                label: __('list_actions.make_default'),
                icon: 'star',
                variant: 'primary',
                method: 'POST',
                path: 'ai/connections/'.$id.'/default',
            );
        }

        $actions[] = QueryRowAction::nav(
            key: 'edit',
            label: __('ui.actions.edit'),
            icon: 'pencil',
            variant: 'primary',
            route: 'ai.connections.edit',
            params: ['id' => $id],
        );

        if (! $model->is_default) {
            $actions[] = QueryRowAction::mutate(
                key: 'delete',
                label: __('ui.actions.delete'),
                icon: 'trash',
                variant: 'danger',
                method: 'DELETE',
                path: 'ai/connections/'.$id,
                confirm: [
                    'title' => __('list_actions.confirm.delete_ai_connection_title'),
                    'text' => __('list_actions.confirm.delete_ai_connection'),
                ],
            );
        }

        return $actions;
    }
}
