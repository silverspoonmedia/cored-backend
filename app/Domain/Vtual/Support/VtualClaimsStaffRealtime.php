<?php

namespace App\Domain\Vtual\Support;

use App\Domain\Vtual\Enums\CreatorClaimStatus;
use App\Domain\Vtual\Events\CreatorClaimStaffChanged;
use App\Domain\Vtual\Models\CreatorClaim;
use App\Support\Broadcasting\BroadcastConnection;

/**
 * Fire the staff claim-queue ping after successful mutations.
 *
 * Distinct from {@see VtualClaimRealtime}: that one carries the owner-facing
 * snapshot (including the bio token) on `vtual.claim.{claimId}`; this one is
 * ids + action + status only, on the staff-gated `vtual.claims` channel.
 */
class VtualClaimsStaffRealtime
{
    public function enabled(): bool
    {
        return BroadcastConnection::isConfigured();
    }

    public function changed(
        string $claimId,
        string $creatorId,
        string $action,
        CreatorClaimStatus|string|null $status = null,
    ): void {
        if (! $this->enabled()) {
            return;
        }

        $statusValue = $status instanceof CreatorClaimStatus
            ? $status->value
            : $status;

        event(new CreatorClaimStaffChanged($claimId, $creatorId, $action, $statusValue));
    }

    public function fromClaim(CreatorClaim $claim, string $action): void
    {
        $this->changed(
            (string) $claim->id,
            (string) $claim->creator_id,
            $action,
            $claim->status,
        );
    }
}
