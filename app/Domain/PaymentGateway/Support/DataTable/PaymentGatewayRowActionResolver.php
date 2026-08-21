<?php

namespace App\Domain\PaymentGateway\Support\DataTable;

use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\Identity\Models\User;
use App\Support\DataTable\ActionButton;
use App\Support\DataTable\Contracts\RowActionResolverInterface;

final class PaymentGatewayRowActionResolver implements RowActionResolverInterface
{
    /**
     * @return list<ActionButton>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof PaymentGateway) {
            return [];
        }

        return [
            new ActionButton(
                key: 'show',
                label: __('ui.datatable.view'),
                method: 'GET',
                url: route('api.v1.payment-gateways.show', $model),
                style: 'info',
                icon: 'fas fa-eye',
            ),
            new ActionButton(
                key: 'edit',
                label: __('ui.datatable.edit'),
                method: 'GET',
                url: \App\Support\SpaPublicUrl::to('/apps/payment-gateways/'.$model->getKey().'/edit'),
                style: 'primary',
                icon: 'fas fa-edit',
            ),
            new ActionButton(
                key: 'delete',
                label: __('ui.datatable.delete'),
                method: 'DELETE',
                url: route('api.v1.payment-gateways.destroy', $model),
                style: 'danger',
                icon: 'fas fa-trash',
            ),
        ];
    }
}
