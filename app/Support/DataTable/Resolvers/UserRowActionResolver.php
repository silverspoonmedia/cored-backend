<?php

namespace App\Support\DataTable\Resolvers;

use App\Domain\Identity\Models\User;
use App\Support\DataTable\ActionButton;
use App\Support\DataTable\Contracts\RowActionResolverInterface;
use Illuminate\Support\Facades\Gate;

final class UserRowActionResolver implements RowActionResolverInterface
{
    /**
     * @return list<ActionButton>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof User || ! $actor instanceof User) {
            return [];
        }

        $actions = [];

        if (Gate::forUser($actor)->allows('view', $model)) {
            $actions[] = new ActionButton(
                key: 'show',
                label: __('ui.datatable.detail'),
                method: 'GET',
                url: route('api.v1.users.show', $model),
                style: 'info',
                icon: 'fas fa-eye',
            );
        }

        if (Gate::forUser($actor)->allows('update', $model)) {
            $actions[] = new ActionButton(
                key: 'edit',
                label: __('ui.datatable.edit'),
                method: 'PATCH',
                url: route('api.v1.users.update', $model),
                style: 'primary',
                icon: 'fas fa-edit',
            );
        }

        if ($actor->id !== $model->id && Gate::forUser($actor)->allows('delete', $model)) {
            $actions[] = new ActionButton(
                key: 'delete',
                label: __('ui.datatable.delete'),
                method: 'DELETE',
                url: route('api.v1.users.destroy', $model),
                style: 'danger',
                icon: 'fas fa-trash',
                confirm: __('ui.datatable.confirm.delete_user'),
            );
        }

        return $actions;
    }
}
