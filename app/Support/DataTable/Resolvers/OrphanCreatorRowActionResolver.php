<?php

namespace App\Support\DataTable\Resolvers;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Services\CreatorService;
use App\Support\DataTable\ActionButton;
use App\Support\DataTable\Contracts\RowActionResolverInterface;
use Illuminate\Support\Facades\Gate;

final class OrphanCreatorRowActionResolver implements RowActionResolverInterface
{
    public function __construct(
        private readonly CreatorService $creators,
    ) {}

    /**
     * @return list<ActionButton>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof Creator || ! $actor instanceof User) {
            return [];
        }

        if (! $this->creators->isClaimable($model)) {
            return [];
        }

        $actions = [];

        if (Gate::forUser($actor)->allows('update', $model)) {
            $actions[] = new ActionButton(
                key: 'edit',
                label: __('ui.datatable.edit'),
                method: 'PATCH',
                url: route('api.v1.vtual.creators.update', $model),
                style: 'primary',
                icon: 'fas fa-edit',
            );
        }

        if (Gate::forUser($actor)->allows('delete', $model)) {
            $actions[] = new ActionButton(
                key: 'delete',
                label: __('ui.datatable.delete'),
                method: 'DELETE',
                url: route('api.v1.vtual.creators.destroy', $model),
                style: 'danger',
                icon: 'fas fa-trash',
                confirm: __('web/vtual-orphans.datatable.confirm_delete'),
            );
        }

        return $actions;
    }
}
