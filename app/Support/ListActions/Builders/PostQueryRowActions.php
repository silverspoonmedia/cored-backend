<?php

namespace App\Support\ListActions\Builders;

use App\Domain\Blog\Models\Post;
use App\Domain\Identity\Models\User;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;

final class PostQueryRowActions implements QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof Post) {
            return [];
        }

        $id = (string) $model->id;
        $title = (string) $model->title;

        $actions = [
            QueryRowAction::nav(
                key: 'edit',
                label: __('ui.actions.edit'),
                icon: 'pencil',
                variant: 'secondary',
                route: 'blog.posts.edit',
                params: ['id' => $id],
            ),
        ];

        if ($actor?->can('manage posts')) {
            $actions[] = QueryRowAction::mutate(
                key: 'delete',
                label: __('ui.actions.delete'),
                icon: 'trash3',
                variant: 'danger',
                method: 'DELETE',
                path: 'blog/posts/'.$id,
                confirm: [
                    'title' => __('list_actions.confirm.delete_named_title', ['name' => $title]),
                    'text' => __('list_actions.confirm.delete_post'),
                ],
            );
        }

        return $actions;
    }
}
