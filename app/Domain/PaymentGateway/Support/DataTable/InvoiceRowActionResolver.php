<?php

namespace App\Domain\PaymentGateway\Support\DataTable;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\Identity\Models\User;
use App\Support\DataTable\ActionButton;
use App\Support\DataTable\Contracts\RowActionResolverInterface;

final class InvoiceRowActionResolver implements RowActionResolverInterface
{
    /**
     * @return list<ActionButton>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof Invoice) {
            return [];
        }

        return [
            new ActionButton(
                key: 'show',
                label: __('ui.datatable.view'),
                method: 'GET',
                url: route('api.v1.invoices.show', $model),
                style: 'info',
                icon: 'fas fa-eye',
            ),
        ];
    }
}
