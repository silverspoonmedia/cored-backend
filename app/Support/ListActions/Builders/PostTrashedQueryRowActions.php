<?php

namespace App\Support\ListActions\Builders;

use App\Domain\Blog\Models\Post;
use App\Domain\Identity\Models\User;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;

final class PostTrashedQueryRowActions implements QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof Post || $actor === null || ! $actor->can('restore posts')) {
            return [];
        }

        return [
            QueryRowAction::mutate(
                key: 'restore',
                label: __('ui.datatable.restore'),
                icon: 'arrow-counterclockwise',
                variant: 'success',
                method: 'POST',
                path: 'blog/posts/'.$model->id.'/restore',
            ),
        ];
    }
}
