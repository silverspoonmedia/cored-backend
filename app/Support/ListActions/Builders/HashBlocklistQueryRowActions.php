<?php

namespace App\Support\ListActions\Builders;

use App\Domain\FileHosting\Models\FileHostingHashBlocklist;
use App\Domain\Identity\Models\User;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;

final class HashBlocklistQueryRowActions implements QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof FileHostingHashBlocklist) {
            return [];
        }

        return [
            QueryRowAction::mutate(
                key: 'remove',
                label: __('list_actions.remove'),
                icon: 'trash',
                variant: 'danger',
                method: 'DELETE',
                path: 'file-hosting/blocklist/'.$model->id,
                confirm: [
                    'title' => __('list_actions.confirm.remove_blocklist_title'),
                    'text' => __('list_actions.confirm.remove_blocklist'),
                ],
            ),
        ];
    }
}
