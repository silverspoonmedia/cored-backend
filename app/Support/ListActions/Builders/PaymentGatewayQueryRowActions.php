<?php

namespace App\Support\ListActions\Builders;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;

final class PaymentGatewayQueryRowActions implements QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof PaymentGateway) {
            return [];
        }

        $id = (string) $model->id;

        return [
            QueryRowAction::nav(
                key: 'edit',
                label: __('ui.actions.edit'),
                icon: 'pencil',
                variant: 'primary',
                route: 'payment-gateways.edit',
                params: ['id' => $id],
            ),
            QueryRowAction::mutate(
                key: 'delete',
                label: __('ui.actions.delete'),
                icon: 'trash3',
                variant: 'danger',
                method: 'DELETE',
                path: 'payment-gateways/'.$id,
                confirm: [
                    'title' => __('ui.datatable.delete'),
                    'text' => __('web/payments.gateways.confirm_delete'),
                ],
            ),
        ];
    }
}
