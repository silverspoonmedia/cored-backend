<?php

namespace App\Support\ListActions\Builders;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Models\VtualEvent;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;

final class VtualEventQueryRowActions implements QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof VtualEvent || $actor === null) {
            return [];
        }

        // Cancel is offered only while the event can still change. The
        // authorize call on DELETE also re-checks org Owner/Admin — a stale
        // action payload can never widen the power.
        if ($model->status->value !== 'scheduled' && $model->status->value !== 'live') {
            return [];
        }

        return [
            QueryRowAction::mutate(
                key: 'cancel',
                label: __('ui.actions.cancel'),
                icon: 'x-circle',
                variant: 'danger',
                method: 'DELETE',
                path: 'vtual/managements/'.$model->management_id.'/events/'.$model->id,
                confirm: [
                    'title' => __('ui.datatable.delete'),
                    'text' => __('list_actions.confirm.cancel_event'),
                ],
            ),
        ];
    }
}
