<?php

namespace App\Domain\PaymentGateway\Support\ListActions;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\Subscription;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;

final class SubscriptionQueryRowActions implements QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof Subscription) {
            return [];
        }

        $id = (string) $model->id;

        return [
            QueryRowAction::nav(
                key: 'view',
                label: __('ui.datatable.view'),
                icon: 'eye',
                variant: 'info',
                route: 'subscriptions.show',
                params: ['id' => $id],
            ),
            QueryRowAction::nav(
                key: 'edit',
                label: __('ui.actions.edit'),
                icon: 'pencil',
                variant: 'primary',
                route: 'subscriptions.edit',
                params: ['id' => $id],
            ),
        ];
    }
}
