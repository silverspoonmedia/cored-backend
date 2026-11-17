<?php

namespace App\Domain\ActivityLog\Support\ListActions;

use App\Domain\Identity\Models\User;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;
use Spatie\Activitylog\Models\Activity;

final class ActivityLogQueryRowActions implements QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof Activity) {
            return [];
        }

        return [
            QueryRowAction::nav(
                key: 'view',
                label: __('list_actions.view_detail'),
                icon: 'eye',
                variant: 'secondary',
                route: 'activity-logs.show',
                params: ['id' => (string) $model->id],
            ),
        ];
    }
}
