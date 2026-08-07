<?php

namespace App\Domain\Vtual\Support;

use App\Domain\Vtual\Events\CreatorClaimProgressChanged;
use App\Domain\Vtual\Http\Resources\CreatorClaimResource;
use App\Domain\Vtual\Models\CreatorClaim;
use App\Support\Broadcasting\BroadcastConnection;
use Illuminate\Http\Request;

/**
 * Fire creator-claim progress events for the claim page Echo client.
 *
 * Gated on {@see BroadcastConnection::isConfigured()} only — independent of
 * `platform.notifications.delivery`.
 */
class VtualClaimRealtime
{
    public function enabled(): bool
    {
        return BroadcastConnection::isConfigured();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function clientConfig(): ?array
    {
        return BroadcastConnection::socketClientConfig();
    }

    public function progress(CreatorClaim $claim, string $phase): void
    {
        if (! $this->enabled()) {
            return;
        }

        $claim->loadMissing(['creator', 'channel.platform', 'user']);

        event(new CreatorClaimProgressChanged(
            claimId: (string) $claim->id,
            phase: $phase,
            claim: $this->claimSnapshot($claim),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function claimSnapshot(CreatorClaim $claim): array
    {
        $claim->loadMissing(['creator', 'channel.platform', 'user']);

        $request = Request::create('/');
        if ($claim->user !== null) {
            $request->setUserResolver(fn () => $claim->user);
        }

        $snapshot = (new CreatorClaimResource($claim))->resolve($request);

        if ($claim->creator !== null && ! empty($claim->creator->slug)) {
            $snapshot['profile_url'] = route('vtual.vtuber.show', $claim->creator->slug);
        }

        return $snapshot;
    }
}
