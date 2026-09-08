<?php

namespace App\Support\ListActions\Builders;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Services\ManagementAccessService;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;

final class ManagementQueryRowActions implements QueryRowActionBuilder
{
    public function __construct(
        private readonly ManagementAccessService $access,
    ) {}

    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof Management || $actor === null) {
            return [];
        }

        // Edit is visible to internal staff (viewAny via roles) AND to every
        // org member (Owner/Admin/Staff — all canView via membership).
        if (! $this->access->canView($model, $actor)) {
            return [];
        }

        $actions = [
            QueryRowAction::nav(
                key: 'edit',
                label: __('ui.actions.edit'),
                icon: 'pencil',
                variant: 'secondary',
                route: 'vtual.managements.edit',
                params: ['id' => (string) $model->id],
            ),
        ];

        // Archiving is reserved to org Owner/Admin (real membership row;
        // internal staff without membership never gets it).
        if ($this->access->canArchive($model, $actor)) {
            $actions[] = QueryRowAction::mutate(
                key: 'archive',
                label: __('ui.actions.delete'),
                icon: 'trash3',
                variant: 'danger',
                method: 'DELETE',
                path: 'vtual/managements/'.$model->id,
                confirm: [
                    'title' => __('ui.datatable.delete'),
                    'text' => __('list_actions.confirm.archive_management'),
                ],
            );
        }

        return $actions;
    }
}
