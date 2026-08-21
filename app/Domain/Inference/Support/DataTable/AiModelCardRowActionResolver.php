<?php

namespace App\Domain\Inference\Support\DataTable;

use App\Domain\Identity\Models\User;
use App\Domain\Inference\Models\AiModelCard;
use App\Support\DataTable\ActionButton;
use App\Support\DataTable\Contracts\RowActionResolverInterface;

final class AiModelCardRowActionResolver implements RowActionResolverInterface
{
    /**
     * @return list<ActionButton>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof AiModelCard) {
            return [];
        }

        return [
            new ActionButton(
                key: 'show',
                label: __('ui.datatable.view'),
                method: 'GET',
                url: \App\Support\SpaPublicUrl::to('/apps/inference/model-cards/'.$model->getKey()),
                style: 'info',
                icon: 'fas fa-eye',
            ),
            new ActionButton(
                key: 'edit',
                label: __('ui.datatable.edit'),
                method: 'GET',
                url: \App\Support\SpaPublicUrl::to('/apps/inference/model-cards/'.$model->getKey().'/edit'),
                style: 'primary',
                icon: 'fas fa-edit',
            ),
            new ActionButton(
                key: 'delete',
                label: __('ui.datatable.delete'),
                method: 'DELETE',
                url: route('api.v1.inference.model-cards.destroy', $model),
                style: 'danger',
                icon: 'fas fa-trash',
            ),
        ];
    }
}
