<?php

namespace App\Support\ListActions\Builders;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\CreatorClaimStatus;
use App\Domain\Vtual\Models\CreatorClaim;
use App\Domain\Vtual\Services\CreatorClaimService;
use App\Support\ListActions\QueryRowAction;
use App\Support\ListActions\QueryRowActionBuilder;

final class VtualClaimQueryRowActions implements QueryRowActionBuilder
{
    public function __construct(
        private readonly CreatorClaimService $claims,
    ) {}

    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array
    {
        if (! $model instanceof CreatorClaim || $actor === null) {
            return [];
        }

        // Closed claims are audit rows: the service rejects every mutation on
        // them. Owner actions additionally require the actor to be the
        // claimant (assertClaimant), staff actions the manage-any permission.
        // The endpoints re-check both, so a stale payload cannot widen power.
        if ($model->status !== CreatorClaimStatus::Pending) {
            return [];
        }

        $actions = [];
        $isOwner = (string) $model->user_id === (string) $actor->id;
        $isStaff = $actor->can('manage any vtual resource');

        if ($isOwner && $this->claims->canQueueVerify($model, $actor)) {
            $actions[] = QueryRowAction::mutate(
                key: 'verify',
                label: __('list_actions.verify_claim'),
                icon: 'patch-check',
                variant: 'primary',
                method: 'POST',
                path: 'vtual/claims/'.$model->id.'/verify',
            );
        }

        if ($isOwner) {
            $actions[] = QueryRowAction::mutate(
                key: 'cancel',
                label: __('ui.actions.cancel'),
                icon: 'x-circle',
                variant: 'secondary',
                method: 'POST',
                path: 'vtual/claims/'.$model->id.'/cancel',
                confirm: [
                    'title' => __('list_actions.confirm.cancel_claim_title'),
                    'text' => __('list_actions.confirm.cancel_claim'),
                ],
            );
        }

        if ($isStaff && (bool) setting_config('vtual.claim.accept_anyway_enabled', false)) {
            $actions[] = QueryRowAction::mutate(
                key: 'accept_anyway',
                label: __('list_actions.accept_anyway'),
                icon: 'check2-circle',
                variant: 'danger',
                method: 'POST',
                path: 'vtual/claims/'.$model->id.'/accept-anyway',
                confirm: [
                    'title' => __('list_actions.confirm.accept_anyway_claim_title'),
                    'text' => __('list_actions.confirm.accept_anyway_claim'),
                ],
            );
        }

        $creator = $model->relationLoaded('creator') ? $model->creator : null;
        if ($creator !== null && (string) $creator->slug !== '') {
            $actions[] = QueryRowAction::nav(
                key: 'view_profile',
                label: __('list_actions.view_profile'),
                icon: 'person-badge',
                variant: 'secondary',
                route: 'vtual.vtuber.show',
                params: ['slug' => (string) $creator->slug],
            );
        }

        return $actions;
    }
}
