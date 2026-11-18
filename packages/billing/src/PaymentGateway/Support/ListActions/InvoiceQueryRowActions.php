<?php

namespace App\Domain\PaymentGateway\Support\ListActions;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;

final class InvoiceQueryRowActions implements QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof Invoice) {
            return [];
        }

        return [
            QueryRowAction::nav(
                key: 'view',
                label: __('ui.datatable.view'),
                icon: 'eye',
                variant: 'primary',
                route: 'invoices.show',
                params: ['id' => (string) $model->id],
            ),
        ];
    }
}
