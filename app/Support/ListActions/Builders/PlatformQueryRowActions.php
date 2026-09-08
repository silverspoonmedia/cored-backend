<?php

namespace App\Support\ListActions\Builders;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Models\Platform;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;

final class PlatformQueryRowActions implements QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof Platform) {
            return [];
        }

        return [
            QueryRowAction::nav(
                key: 'manage',
                label: __('list_actions.manage'),
                icon: 'gear',
                variant: 'primary',
                route: 'vtual.platform-credentials.platform',
                params: ['platformId' => (string) $model->id],
            ),
        ];
    }
}
