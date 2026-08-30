<?php

namespace App\Support\ListActions\Builders;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Models\PlatformCredential;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;

final class PlatformCredentialQueryRowActions implements QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof PlatformCredential || $actor === null || ! $actor->can('manage vtual platform credentials')) {
            return [];
        }

        $id = (string) $model->id;

        return [
            QueryRowAction::mutate(
                key: 'test',
                label: __('list_actions.test'),
                icon: 'lightning',
                variant: 'success',
                method: 'POST',
                path: 'vtual/admin/credentials/'.$id.'/test',
            ),
            QueryRowAction::mutate(
                key: 'revoke',
                label: __('list_actions.revoke'),
                icon: 'shield-x',
                variant: 'warning',
                method: 'POST',
                path: 'vtual/admin/credentials/'.$id.'/revoke',
                confirm: [
                    'title' => __('list_actions.confirm.revoke_credential_title'),
                    'text' => __('list_actions.confirm.revoke_credential'),
                ],
            ),
            QueryRowAction::nav(
                key: 'edit',
                label: __('ui.actions.edit'),
                icon: 'pencil',
                variant: 'primary',
                route: 'vtual.platform-credentials.edit',
                params: ['id' => $id],
            ),
            QueryRowAction::mutate(
                key: 'delete',
                label: __('ui.actions.delete'),
                icon: 'trash',
                variant: 'danger',
                method: 'DELETE',
                path: 'vtual/admin/credentials/'.$id,
                confirm: [
                    'title' => __('list_actions.confirm.delete_credential_title'),
                    'text' => __('list_actions.confirm.delete_platform_credential'),
                ],
            ),
        ];
    }
}
