<?php

namespace App\Support\ListActions\Builders;

use App\Domain\ErrorLog\Models\ErrorLog;
use App\Domain\Identity\Models\User;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;

final class ErrorLogQueryRowActions implements QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof ErrorLog) {
            return [];
        }

        return [
            QueryRowAction::nav(
                key: 'view',
                label: __('list_actions.view_detail'),
                icon: 'eye',
                variant: 'secondary',
                route: 'error-logs.show',
                params: ['id' => (string) $model->id],
            ),
        ];
    }
}
