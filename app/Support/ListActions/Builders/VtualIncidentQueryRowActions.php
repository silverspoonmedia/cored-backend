<?php

namespace App\Support\ListActions\Builders;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\IncidentStatus;
use App\Domain\Vtual\Models\VtualIncident;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;

final class VtualIncidentQueryRowActions implements QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof VtualIncident || $actor === null) {
            return [];
        }

        // Both mutations are gated on `manage vtual incident` by the route, and
        // the endpoint re-checks it — a stale payload cannot widen power.
        // `link` is offered to any viewer: the point of the queue is to get the
        // operator to the broken thing.
        $canManage = $actor->can('manage vtual incident');

        $actions = [];

        if ($canManage && $model->status === IncidentStatus::Open) {
            $actions[] = QueryRowAction::mutate(
                key: 'acknowledge',
                label: __('list_actions.acknowledge_incident'),
                icon: 'eye',
                variant: 'secondary',
                method: 'PATCH',
                path: 'vtual/admin/incidents/'.$model->id.'/status',
            );
        }

        if ($canManage && $model->status !== IncidentStatus::Resolved) {
            $actions[] = QueryRowAction::mutate(
                key: 'resolve',
                label: __('list_actions.resolve_incident'),
                icon: 'check2-circle',
                variant: 'primary',
                method: 'PATCH',
                path: 'vtual/admin/incidents/'.$model->id.'/status',
                confirm: [
                    'title' => __('list_actions.confirm.resolve_incident_title'),
                    'text' => __('list_actions.confirm.resolve_incident'),
                ],
            );
        }

        $link = $this->resolveLink($model);

        if ($link !== null) {
            $actions[] = $link;
        }

        return $actions;
    }

    /**
     * Send the operator to the subject the incident is about: the credential
     * that stopped authenticating, or the profile that owns the failing channel.
     * An incident with neither subject is a platform-wide failure — nothing
     * specific to open, so no action.
     */
    private function resolveLink(VtualIncident $incident): ?QueryRowAction
    {
        if ($incident->credential_id !== null && $incident->platform_id !== null) {
            return QueryRowAction::nav(
                key: 'link',
                label: __('list_actions.open_credential'),
                icon: 'key',
                variant: 'secondary',
                route: 'vtual.platform-credentials.platform',
                params: ['platformId' => (string) $incident->platform_id],
            );
        }

        $channel = $incident->relationLoaded('channel') ? $incident->channel : null;

        if ($channel !== null && $channel->creator_id !== null) {
            return QueryRowAction::nav(
                key: 'link',
                label: __('list_actions.open_profile'),
                icon: 'person-badge',
                variant: 'secondary',
                route: 'vtual.profiles.show',
                params: ['id' => (string) $channel->creator_id],
            );
        }

        return null;
    }
}
