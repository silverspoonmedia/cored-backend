<?php

namespace App\Support\ListActions\Builders;

use App\Domain\Identity\Models\User;
use App\Domain\Service\Models\Currency;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;

final class CurrencyQueryRowActions implements QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof Currency || $actor === null || ! $actor->can('manage settings')) {
            return [];
        }

        $code = (string) $model->code;
        $actions = [
            QueryRowAction::mutate(
                key: 'toggle',
                label: $model->is_active
                    ? __('list_actions.deactivate')
                    : __('list_actions.activate'),
                icon: $model->is_active ? 'pause' : 'play',
                variant: 'secondary',
                method: 'PUT',
                path: 'currencies/'.$code,
            ),
        ];

        if (! $model->is_settlement) {
            $actions[] = QueryRowAction::mutate(
                key: 'set_settlement',
                label: __('list_actions.set_settlement'),
                icon: 'check2-circle',
                variant: 'primary',
                method: 'PUT',
                path: 'currencies/'.$code,
            );
        }

        return $actions;
    }
}
