<?php

namespace App\Support\ListActions\Builders;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Models\Creator;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;
use App\Support\SpaPublicUrl;

final class OwnedCreatorQueryRowActions implements QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof Creator) {
            return [];
        }

        $actions = [
            QueryRowAction::nav(
                key: 'edit',
                label: __('ui.actions.edit'),
                icon: 'pencil',
                variant: 'secondary',
                route: 'vtual.profiles.show',
                params: ['id' => (string) $model->id],
            ),
        ];

        $url = SpaPublicUrl::vtuberProfile($model->slug);
        if (is_string($url) && $url !== '') {
            $actions[] = QueryRowAction::href(
                key: 'view',
                label: __('list_actions.view_profile'),
                icon: 'box-arrow-up-right',
                variant: 'info',
                href: $url,
                target: '_blank',
            );
        }

        return $actions;
    }
}
