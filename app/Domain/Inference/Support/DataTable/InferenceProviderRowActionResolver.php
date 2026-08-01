<?php

namespace App\Domain\Inference\Support\DataTable;

use App\Domain\Identity\Models\User;
use App\Domain\Inference\Models\InferenceProvider;
use App\Support\DataTable\ActionButton;
use App\Support\DataTable\Contracts\RowActionResolverInterface;

final class InferenceProviderRowActionResolver implements RowActionResolverInterface
{
    /**
     * @return list<ActionButton>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof InferenceProvider) {
            return [];
        }

        return [
            new ActionButton(
                key: 'show',
                label: __('ui.datatable.view'),
                method: 'GET',
                url: route('inference.providers.show', $model),
                style: 'info',
                icon: 'fas fa-eye',
            ),
            new ActionButton(
                key: 'edit',
                label: __('ui.datatable.edit'),
                method: 'GET',
                url: route('inference.providers.edit', $model),
                style: 'primary',
                icon: 'fas fa-edit',
            ),
            new ActionButton(
                key: 'delete',
                label: __('ui.datatable.delete'),
                method: 'DELETE',
                url: route('api.v1.inference.providers.destroy', $model),
                style: 'danger',
                icon: 'fas fa-trash',
            ),
        ];
    }
}
