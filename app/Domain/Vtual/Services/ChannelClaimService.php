<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\CreatorClaimClosedReason;
use App\Domain\Vtual\Enums\CreatorClaimStatus;
use App\Domain\Vtual\Enums\CreatorClaimVerifiedVia;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Support\ChannelBioTokenVerifier;
use App\Domain\Vtual\Integrations\Support\ChannelProfilePlatformGuard;
use App\Domain\Vtual\Integrations\Support\ChannelProfileResolver;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\ChannelClaim;
use App\Domain\Vtual\Models\Platform;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Attach a platform channel to a creator after bio-token proof (or staff Accept Anyway).
 * Pending claims live in vtual_channel_claims; channel row is created only on success.
 */
class ChannelClaimService
{
    public function __construct(
        private readonly CreatorAccessService $creatorAccess,
        private readonly ChannelProfileResolver $profiles,
        private readonly ChannelBioTokenVerifier $bio,
        private readonly ChannelService $channels,
    ) {}

    /**
     * @return Collection<int, ChannelClaim>
     */
    public function listForCreator(User $actor, string $creatorId): Collection
    {
        $creator = $this->creatorAccess->findOrFail($creatorId);
        $this->creatorAccess->assertCanView($creator, $actor);

        return ChannelClaim::query()
            ->where('creator_id', $creator->id)
            ->where('status', CreatorClaimStatus::Pending)
            ->with(['platform', 'user'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function show(User $actor, string $claimId): ChannelClaim
    {
        $claim = ChannelClaim::query()
            ->with(['platform', 'creator', 'user', 'channel'])
            ->findOrFail($claimId);

        $this->assertCanViewClaim($claim, $actor);

        return $claim;
    }

    /**
     * @param  array{platform_key: string, profile: string}  $data
     */
    public function start(User $actor, string $creatorId, array $data): ChannelClaim
    {
        $creator = $this->creatorAccess->findOrFail($creatorId);
        $this->creatorAccess->assertCanMutate($creator, $actor);

        $platform = Platform::query()->where('key', $data['platform_key'] ?? null)->first();
        if ($platform === null) {
            throw new VtualException(__('api/vtual.channel.platform_unknown'), 422);
        }
        $this->bio->assertPlatformEnabled($platform);

        $profile = trim((string) ($data['profile'] ?? ''));
        if ($profile === '') {
            throw new VtualException(__('api/vtual.channel.profile_required'), 422);
        }

        $platformKey = $this->bio->platformKeyFromModel($platform);
        ChannelProfilePlatformGuard::assertMatchesPlatform($platformKey, $profile);
        $dto = $this->profiles->resolve($platformKey, $profile);

        $exists = Channel::query()
            ->where('platform_id', $platform->id)
            ->where('external_id', $dto->externalId)
            ->exists();
        if ($exists) {
            throw new VtualException(__('api/vtual.channel.external_id_taken'), 422);
        }

        // Another actor already has a pending claim for this platform identity.
        $pendingOther = ChannelClaim::query()
            ->where('platform_id', $platform->id)
            ->where('external_id', $dto->externalId)
            ->where('status', CreatorClaimStatus::Pending)
            ->where('user_id', '!=', $actor->id)
            ->exists();
        if ($pendingOther) {
            throw new VtualException(__('api/vtual.channel_claim.pending_other'), 422);
        }

        return DB::transaction(function () use ($actor, $creator, $platform, $dto, $profile): ChannelClaim {
            // Restart same attach: cancel actor's prior pending for this identity.
            ChannelClaim::query()
                ->where('user_id', $actor->id)
                ->where('creator_id', $creator->id)
                ->where('platform_id', $platform->id)
                ->where('external_id', $dto->externalId)
                ->where('status', CreatorClaimStatus::Pending)
                ->update([
                    'status' => CreatorClaimStatus::Cancelled->value,
                    'closed_reason' => CreatorClaimClosedReason::UserCancel->value,
                ]);

            $ttlHours = max(1, (int) setting_config('vtual.channel_claim.ttl_hours', setting_config('vtual.claim.ttl_hours', 24)));
            $token = $this->bio->mintToken('vtual.channel_claim');

            $claim = new ChannelClaim([
                'user_id' => $actor->id,
                'creator_id' => $creator->id,
                'platform_id' => $platform->id,
                'external_id' => $dto->externalId,
                'profile_input' => $profile,
                'handle' => $dto->handle,
                'display_name' => $dto->displayName,
                'url' => $dto->url,
                'token' => $token,
                'status' => CreatorClaimStatus::Pending,
                'closed_reason' => null,
                'attempt_count' => 0,
                'expires_at' => now()->addHours($ttlHours),
            ]);
            $claim->id = (string) Str::uuid7();
            $claim->save();

            return $claim->load(['platform', 'creator', 'user']);
        });
    }

    public function verify(User $actor, string $claimId): ChannelClaim
    {
        $claim = $this->lockPendingClaim($claimId);
        $this->assertClaimant($claim, $actor);
        $this->assertNotExpired($claim);
        $this->assertExternalIdStillFree($claim);
        $this->assertRateLimit($claim);

        $claim->forceFill([
            'attempt_count' => (int) $claim->attempt_count + 1,
            'last_attempt_at' => now(),
        ])->save();

        $platform = $claim->platform;
        if ($platform === null) {
            throw new VtualException(__('api/vtual.channel.platform_unknown'), 422);
        }

        $result = $this->bio->check(
            $this->bio->platformKeyFromModel($platform),
            (string) $claim->external_id,
            (string) $claim->token,
        );

        if (! $result['found']) {
            throw new VtualException(__('api/vtual.channel_claim.token_not_found'), 422);
        }

        return $this->complete($claim, CreatorClaimVerifiedVia::Bio, null, $result['dto']);
    }

    public function acceptAnyway(User $staff, string $claimId): ChannelClaim
    {
        if (! $staff->can('manage any vtual resource')) {
            throw new VtualException(__('api/vtual.policy.denied'), 403);
        }

        if (! (bool) setting_config('vtual.channel_claim.accept_anyway_enabled', setting_config('vtual.claim.accept_anyway_enabled', false))) {
            throw new VtualException(__('api/vtual.channel_claim.accept_anyway_disabled'), 422);
        }

        $claim = $this->lockPendingClaim($claimId);
        $this->assertNotExpired($claim);
        $this->assertExternalIdStillFree($claim);

        $platform = $claim->platform;
        if ($platform === null) {
            throw new VtualException(__('api/vtual.channel.platform_unknown'), 422);
        }

        // Re-resolve for fresh snapshot (no bio token required).
        $dto = $this->profiles->resolveByExternalId(
            $this->bio->platformKeyFromModel($platform),
            (string) $claim->external_id,
        );

        return $this->complete($claim, CreatorClaimVerifiedVia::AcceptAnyway, $staff, $dto);
    }

    public function cancel(User $actor, string $claimId): ChannelClaim
    {
        $claim = $this->lockPendingClaim($claimId);
        $this->assertClaimant($claim, $actor);

        $this->closeClaim($claim, CreatorClaimStatus::Cancelled, CreatorClaimClosedReason::UserCancel);

        return $claim->fresh(['platform', 'creator', 'user']) ?? $claim;
    }

    private function complete(
        ChannelClaim $claim,
        CreatorClaimVerifiedVia $via,
        ?User $staff,
        \App\Domain\Vtual\Integrations\Dto\PlatformChannelDto $dto,
    ): ChannelClaim {
        return DB::transaction(function () use ($claim, $via, $staff, $dto): ChannelClaim {
            $locked = ChannelClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== CreatorClaimStatus::Pending) {
                throw new VtualException(__('api/vtual.channel_claim.not_pending'), 422);
            }

            $platform = Platform::query()->whereKey($locked->platform_id)->firstOrFail();

            $channel = $this->channels->activateFromDto(
                (string) $locked->creator_id,
                $platform,
                $dto,
            );

            $locked->forceFill([
                'status' => CreatorClaimStatus::Verified,
                'closed_reason' => null,
                'verified_at' => now(),
                'verified_via' => $via,
                'verified_by_user_id' => $staff?->id,
                'channel_id' => $channel->id,
                'handle' => $dto->handle,
                'display_name' => $dto->displayName,
                'url' => $dto->url,
            ])->save();

            // Close other pendings for same platform identity.
            ChannelClaim::query()
                ->where('platform_id', $locked->platform_id)
                ->where('external_id', $locked->external_id)
                ->where('status', CreatorClaimStatus::Pending)
                ->whereKeyNot($locked->id)
                ->update([
                    'status' => CreatorClaimStatus::Cancelled->value,
                    'closed_reason' => CreatorClaimClosedReason::Superseded->value,
                ]);

            return $locked->fresh(['platform', 'creator', 'user', 'channel.platform']) ?? $locked;
        });
    }

    private function lockPendingClaim(string $claimId): ChannelClaim
    {
        $claim = ChannelClaim::query()
            ->with(['platform', 'creator', 'user'])
            ->findOrFail($claimId);

        if ($claim->status !== CreatorClaimStatus::Pending) {
            throw new VtualException(__('api/vtual.channel_claim.not_pending'), 422);
        }

        return $claim;
    }

    private function assertCanViewClaim(ChannelClaim $claim, User $actor): void
    {
        if ((string) $claim->user_id === (string) $actor->id
            || $actor->can('manage any vtual resource')) {
            return;
        }

        $this->creatorAccess->assertCanView($claim->creator, $actor);
    }

    private function assertClaimant(ChannelClaim $claim, User $actor): void
    {
        if ((string) $claim->user_id !== (string) $actor->id) {
            throw new VtualException(__('api/vtual.policy.access_denied'), 403);
        }
    }

    private function assertNotExpired(ChannelClaim $claim): void
    {
        if ($claim->expires_at !== null && $claim->expires_at->isPast()) {
            $this->closeClaim($claim, CreatorClaimStatus::Expired, CreatorClaimClosedReason::Expired);
            throw new VtualException(__('api/vtual.channel_claim.expired'), 422);
        }
    }

    private function assertExternalIdStillFree(ChannelClaim $claim): void
    {
        $taken = Channel::query()
            ->where('platform_id', $claim->platform_id)
            ->where('external_id', $claim->external_id)
            ->exists();

        if ($taken) {
            $this->closeClaim($claim, CreatorClaimStatus::Cancelled, CreatorClaimClosedReason::Superseded);
            throw new VtualException(__('api/vtual.channel.external_id_taken'), 422);
        }
    }

    private function assertRateLimit(ChannelClaim $claim): void
    {
        $maxAttempts = max(1, (int) setting_config('vtual.channel_claim.max_attempts', setting_config('vtual.claim.max_attempts', 20)));
        if ((int) $claim->attempt_count >= $maxAttempts) {
            throw new VtualException(__('api/vtual.channel_claim.max_attempts'), 422);
        }

        $cooldown = max(0, (int) setting_config('vtual.channel_claim.verify_cooldown_seconds', setting_config('vtual.claim.verify_cooldown_seconds', 60)));
        if ($cooldown > 0
            && $claim->last_attempt_at !== null
            && $claim->last_attempt_at->gt(now()->subSeconds($cooldown))) {
            throw new VtualException(__('api/vtual.channel_claim.cooldown', [
                'seconds' => $cooldown,
            ]), 422);
        }
    }

    private function closeClaim(
        ChannelClaim $claim,
        CreatorClaimStatus $status,
        CreatorClaimClosedReason $reason,
    ): void {
        $claim->forceFill([
            'status' => $status,
            'closed_reason' => $reason,
        ])->save();
    }
}
