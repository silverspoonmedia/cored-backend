<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\UserOAuthAccountService;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\CreatorClaimClosedReason;
use App\Domain\Vtual\Enums\CreatorClaimStatus;
use App\Domain\Vtual\Enums\CreatorClaimVerifiedVia;
use App\Domain\Vtual\Enums\CreatorClaimVerifyPoolStatus;
use App\Domain\Vtual\Enums\CreatorMemberRole;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Support\ChannelProfileResolver;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\CreatorClaim;
use App\Domain\Vtual\Models\CreatorClaimBlock;
use App\Domain\Vtual\Models\CreatorMember;
use App\Domain\Vtual\Support\VtualClaimRealtime;
use App\Support\Product\Services\ProductActivationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Claim orphan creator via platform channel bio token (or staff Accept Anyway).
 * Winning claim closes competing pendings (rows kept as audit log).
 */
class CreatorClaimService
{
    public function __construct(
        private readonly CreatorService $creators,
        private readonly ChannelProfileResolver $profiles,
        private readonly CreatorClaimVerifyPoolService $verifyPool,
        private readonly UserOAuthAccountService $oauthAccounts,
        private readonly DiscordConnectionDiscoveryService $discordDiscovery,
    ) {}

    /**
     * @return Collection<int, Creator>
     */
    public function listClaimableCreators(): Collection
    {
        return $this->creators->listClaimable();
    }

    /**
     * Pending claims for the actor.
     *
     * @return Collection<int, CreatorClaim>
     */
    public function listMine(User $actor): Collection
    {
        return CreatorClaim::query()
            ->where('user_id', $actor->id)
            ->where('status', CreatorClaimStatus::Pending)
            ->with(['creator', 'channel.platform'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * All pending claims (staff queue).
     *
     * @return Collection<int, CreatorClaim>
     */
    public function listPendingForStaff(User $staff): Collection
    {
        if (! $staff->can('manage any vtual resource')) {
            throw new VtualException(__('api/vtual.policy.denied'), 403);
        }

        return CreatorClaim::query()
            ->where('status', CreatorClaimStatus::Pending)
            ->with(['creator', 'channel.platform', 'user'])
            ->orderBy('created_at')
            ->get();
    }

    public function show(User $actor, string $claimId): CreatorClaim
    {
        $claim = CreatorClaim::query()
            ->with(['creator', 'channel.platform', 'user'])
            ->findOrFail($claimId);

        if ((string) $claim->user_id !== (string) $actor->id
            && ! $actor->can('manage any vtual resource')) {
            throw new VtualException(__('api/vtual.policy.access_denied'), 403);
        }

        return $claim;
    }

    public function start(User $actor, string $creatorId, string $channelId): CreatorClaim
    {
        $channel = Channel::query()->with('platform')->findOrFail($channelId);

        $claim = DB::transaction(function () use ($actor, $creatorId, $channel): CreatorClaim {
            $creator = Creator::query()->whereKey($creatorId)->lockForUpdate()->firstOrFail();

            if ($this->isBlocked($actor, (string) $creator->id)) {
                throw new VtualException(__('api/vtual.claim.blocked'), 422);
            }

            if (! $this->creators->isClaimable($creator)) {
                throw new VtualException(__('api/vtual.claim.not_claimable'), 422);
            }

            if ((string) $channel->creator_id !== (string) $creator->id) {
                throw new VtualException(__('api/vtual.claim.channel_mismatch'), 422);
            }

            $maxPending = max(1, (int) setting_config('vtual.claim.max_pending_per_user', 5));
            $pendingCount = CreatorClaim::query()
                ->where('user_id', $actor->id)
                ->where('status', CreatorClaimStatus::Pending)
                ->count();

            // Restart same creator: cancel old pending first, does not count against limit as net +0.
            $existingSame = CreatorClaim::query()
                ->where('user_id', $actor->id)
                ->where('creator_id', $creator->id)
                ->where('status', CreatorClaimStatus::Pending)
                ->count();

            if ($pendingCount - $existingSame >= $maxPending) {
                throw new VtualException(__('api/vtual.claim.max_pending', [
                    'max' => $maxPending,
                ]), 422);
            }

            CreatorClaim::query()
                ->where('user_id', $actor->id)
                ->where('creator_id', $creator->id)
                ->where('status', CreatorClaimStatus::Pending)
                ->update([
                    'status' => CreatorClaimStatus::Cancelled->value,
                    'closed_reason' => CreatorClaimClosedReason::UserCancel->value,
                ]);

            $ttlHours = max(1, (int) setting_config('vtual.claim.ttl_hours', 24));
            $prefix = (string) setting_config('vtual.claim.token_prefix', 'vtual-claim-');
            $bytes = max(3, (int) setting_config('vtual.claim.token_bytes', 6));
            $token = $prefix.bin2hex(random_bytes($bytes));

            $claim = new CreatorClaim([
                'user_id' => $actor->id,
                'creator_id' => $creator->id,
                'channel_id' => $channel->id,
                'token' => $token,
                'status' => CreatorClaimStatus::Pending,
                'closed_reason' => null,
                'attempt_count' => 0,
                'expires_at' => now()->addHours($ttlHours),
            ]);
            $claim->id = (string) Str::uuid7();
            $claim->save();

            return $claim->load(['creator', 'channel.platform']);
        });

        app(VtualClaimRealtime::class)->progress($claim, 'started');

        return $claim;
    }

    public function verify(User $actor, string $claimId): CreatorClaim
    {
        return $this->queueVerify($actor, $claimId);
    }

    public function queueVerify(User $actor, string $claimId): CreatorClaim
    {
        $claim = $this->lockPendingClaim($claimId);
        $this->assertClaimant($claim, $actor);
        $this->assertNotExpired($claim);

        if ($this->isBlocked($actor, (string) $claim->creator_id)) {
            throw new VtualException(__('api/vtual.claim.blocked'), 422);
        }

        // Already owned by someone else: close without burning rate-limit attempts.
        if (! $this->creators->isClaimable($claim->creator)) {
            $this->closeClaim($claim, CreatorClaimStatus::Cancelled, CreatorClaimClosedReason::Superseded);
            throw new VtualException(__('api/vtual.claim.already_owned'), 422);
        }

        $this->assertRateLimit($claim);

        $claim->forceFill([
            'attempt_count' => (int) $claim->attempt_count + 1,
            'last_attempt_at' => now(),
        ])->save();

        $this->verifyPool->enqueue($claim->fresh(['creator', 'channel.platform', 'user']) ?? $claim);

        $fresh = $claim->fresh(['creator', 'channel.platform', 'user']) ?? $claim;
        app(VtualClaimRealtime::class)->progress($fresh, 'verify_queued');

        return $fresh;
    }

    public function verifyViaDiscord(User $actor, string $claimId): CreatorClaim
    {
        if (! (bool) setting_config('vtual.claim.discord_verify_enabled', false)) {
            throw new VtualException(__('api/vtual.claim.discord_verify_disabled'), 422);
        }

        $claim = $this->lockPendingClaim($claimId);
        $this->assertClaimant($claim, $actor);
        $this->assertNotExpired($claim);

        if ($this->isBlocked($actor, (string) $claim->creator_id)) {
            throw new VtualException(__('api/vtual.claim.blocked'), 422);
        }

        if (! $this->creators->isClaimable($claim->creator)) {
            $this->closeClaim($claim, CreatorClaimStatus::Cancelled, CreatorClaimClosedReason::Superseded);
            throw new VtualException(__('api/vtual.claim.already_owned'), 422);
        }

        if ($this->verifyPool->openQueueStatusForClaim($claim->id) !== null) {
            throw new VtualException(__('api/vtual.claim.verify_already_queued'), 422);
        }

        if (! $this->oauthAccounts->hasDiscordConnection($actor)) {
            throw new VtualException(__('api/vtual.claim.discord_not_connected'), 422);
        }

        $this->assertRateLimit($claim);

        $claim->forceFill([
            'attempt_count' => (int) $claim->attempt_count + 1,
            'last_attempt_at' => now(),
        ])->save();

        try {
            $connections = $this->oauthAccounts->connectionsForOwnershipVerify(
                $actor,
                fn (array $eligible): bool => $eligible !== [],
            );
        } catch (\Throwable) {
            $this->handleFailedVerifyAttempt($claim);
            $fresh = $claim->fresh(['creator', 'channel.platform', 'user']) ?? $claim;
            $phase = $fresh->status === CreatorClaimStatus::Cancelled
                && $fresh->closed_reason === CreatorClaimClosedReason::MaxAttempts
                ? 'max_attempts_blocked'
                : 'verify_failed';
            app(VtualClaimRealtime::class)->progress($fresh, $phase);

            throw new VtualException(__('api/vtual.claim.discord_connections_failed'), 422);
        }

        $discovery = $this->discordDiscovery->apply($claim->creator, $connections);

        if ($discovery->contractChannel === null) {
            $this->handleFailedVerifyAttempt($claim);
            $fresh = $claim->fresh(['creator', 'channel.platform', 'user']) ?? $claim;
            $phase = $fresh->status === CreatorClaimStatus::Cancelled
                && $fresh->closed_reason === CreatorClaimClosedReason::MaxAttempts
                ? 'max_attempts_blocked'
                : 'verify_failed';
            app(VtualClaimRealtime::class)->progress($fresh, $phase);

            return $fresh;
        }

        $completed = $this->complete(
            $claim,
            CreatorClaimVerifiedVia::DiscordConnection,
            null,
            $discovery->contractChannel,
        );
        app(VtualClaimRealtime::class)->progress($completed, 'verified');

        return $completed;
    }

    public function processQueuedVerify(CreatorClaim $claim, ?string $description): CreatorClaimProcessResult
    {
        $claim = $this->lockPendingClaim($claim->id);

        if (! $this->creators->isClaimable($claim->creator)) {
            $this->closeClaim($claim, CreatorClaimStatus::Cancelled, CreatorClaimClosedReason::Superseded);
            $this->verifyPool->finalizeOpenItemForClaim((string) $claim->id, CreatorClaimVerifyPoolStatus::Done);
            app(VtualClaimRealtime::class)->progress($claim->fresh(['creator', 'channel.platform', 'user']) ?? $claim, 'cancelled');

            return CreatorClaimProcessResult::TokenNotFound;
        }

        $token = (string) $claim->token;

        if ($token === '' || $description === null || ! str_contains($description, $token)) {
            $this->handleFailedVerifyAttempt($claim);
            $fresh = $claim->fresh(['creator', 'channel.platform', 'user']) ?? $claim;
            $phase = $fresh->status === CreatorClaimStatus::Cancelled
                && $fresh->closed_reason === CreatorClaimClosedReason::MaxAttempts
                ? 'max_attempts_blocked'
                : 'verify_failed';
            $this->verifyPool->finalizeOpenItemForClaim(
                (string) $fresh->id,
                CreatorClaimVerifyPoolStatus::Done,
                $phase === 'verify_failed' ? 'Token not found in channel description.' : null,
            );
            app(VtualClaimRealtime::class)->progress($fresh, $phase);

            return CreatorClaimProcessResult::TokenNotFound;
        }

        $completed = $this->complete($claim, CreatorClaimVerifiedVia::Bio, null);
        $this->verifyPool->finalizeOpenItemForClaim((string) $completed->id, CreatorClaimVerifyPoolStatus::Done);
        app(VtualClaimRealtime::class)->progress($completed, 'verified');

        return CreatorClaimProcessResult::Verified;
    }

    public function isBlocked(User $actor, string $creatorId): bool
    {
        return $this->blockedUntil($actor, $creatorId) !== null;
    }

    public function blockedUntil(User $actor, string $creatorId): ?\Illuminate\Support\Carbon
    {
        /** @var CreatorClaimBlock|null $block */
        $block = CreatorClaimBlock::query()
            ->where('user_id', $actor->id)
            ->where('creator_id', $creatorId)
            ->first();

        if ($block === null || $block->blocked_until === null || $block->blocked_until->isPast()) {
            return null;
        }

        return $block->blocked_until;
    }

    public function maxAttempts(): int
    {
        return max(1, (int) setting_config('vtual.claim.max_attempts', 3));
    }

    public function verifyQueueStatus(CreatorClaim $claim): ?string
    {
        $status = $this->verifyPool->openQueueStatusForClaim($claim->id);

        return $status?->value;
    }

    public function canQueueVerify(CreatorClaim $claim, User $actor): bool
    {
        if ($claim->status !== CreatorClaimStatus::Pending) {
            return false;
        }

        if ($this->isBlocked($actor, (string) $claim->creator_id)) {
            return false;
        }

        if ($this->verifyPool->openQueueStatusForClaim($claim->id) !== null) {
            return false;
        }

        if ((int) $claim->attempt_count >= $this->maxAttempts()) {
            return false;
        }

        $cooldown = max(0, (int) setting_config('vtual.claim.verify_cooldown_seconds', 60));
        if ($cooldown > 0
            && $claim->last_attempt_at !== null
            && $claim->last_attempt_at->gt(now()->subSeconds($cooldown))) {
            return false;
        }

        return true;
    }

    private function handleFailedVerifyAttempt(CreatorClaim $claim): void
    {
        if ((int) $claim->attempt_count >= $this->maxAttempts()) {
            $this->closeClaim($claim, CreatorClaimStatus::Cancelled, CreatorClaimClosedReason::MaxAttempts);
            $this->applyBlock($claim);
        }
    }

    private function applyBlock(CreatorClaim $claim): void
    {
        $hours = max(1, (int) setting_config('vtual.claim.block_hours_after_max_attempts', 24));
        $until = now()->addHours($hours);

        CreatorClaimBlock::query()->updateOrCreate(
            [
                'user_id' => $claim->user_id,
                'creator_id' => $claim->creator_id,
            ],
            [
                'blocked_until' => $until,
            ],
        );
    }

    public function acceptAnyway(User $staff, string $claimId): CreatorClaim
    {
        if (! $staff->can('manage any vtual resource')) {
            throw new VtualException(__('api/vtual.policy.denied'), 403);
        }

        if (! (bool) setting_config('vtual.claim.accept_anyway_enabled', false)) {
            throw new VtualException(__('api/vtual.claim.accept_anyway_disabled'), 422);
        }

        $claim = $this->lockPendingClaim($claimId);
        $this->assertNotExpired($claim);

        if (! $this->creators->isClaimable($claim->creator)) {
            $this->closeClaim($claim, CreatorClaimStatus::Cancelled, CreatorClaimClosedReason::Superseded);
            throw new VtualException(__('api/vtual.claim.already_owned'), 422);
        }

        return $this->complete($claim, CreatorClaimVerifiedVia::AcceptAnyway, $staff);
    }

    public function cancel(User $actor, string $claimId): CreatorClaim
    {
        $claim = $this->lockPendingClaim($claimId);
        $this->assertClaimant($claim, $actor);

        $this->closeClaim($claim, CreatorClaimStatus::Cancelled, CreatorClaimClosedReason::UserCancel);

        $fresh = $claim->fresh(['creator', 'channel.platform']) ?? $claim;
        app(VtualClaimRealtime::class)->progress($fresh, 'cancelled');

        return $fresh;
    }

    private function complete(
        CreatorClaim $claim,
        CreatorClaimVerifiedVia $via,
        ?User $staff,
        ?Channel $verifiedChannel = null,
    ): CreatorClaim {
        return DB::transaction(function () use ($claim, $via, $staff, $verifiedChannel): CreatorClaim {
            $locked = CreatorClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== CreatorClaimStatus::Pending) {
                throw new VtualException(__('api/vtual.claim.not_pending'), 422);
            }

            $creator = Creator::query()->whereKey($locked->creator_id)->lockForUpdate()->firstOrFail();

            if (! $this->creators->isClaimable($creator)) {
                $this->closeClaim($locked, CreatorClaimStatus::Cancelled, CreatorClaimClosedReason::Superseded);
                throw new VtualException(__('api/vtual.claim.already_owned'), 422);
            }

            $user = User::query()->whereKey($locked->user_id)->firstOrFail();

            CreatorMember::query()->create([
                'creator_id' => $creator->id,
                'user_id' => $user->id,
                'role' => CreatorMemberRole::Owner,
            ]);

            $roleName = (string) setting_config('vtual.claim.vtuber_role', 'Vtual vTuber');
            if (! $user->hasRole($roleName)) {
                $user->assignRole($roleName);
                if (app()->bound(\App\Domain\Navigation\Repositories\Contracts\MenuRepositoryInterface::class)) {
                    app(\App\Domain\Navigation\Repositories\Contracts\MenuRepositoryInterface::class)
                        ->forgetPresentedTreeForUserId((string) $user->id);
                }
            }

            app(ProductActivationService::class)->activateImplicit($user, 'Vtual', 'claim_success');

            $locked->forceFill([
                'status' => CreatorClaimStatus::Verified,
                'closed_reason' => null,
                'verified_at' => now(),
                'verified_via' => $via,
                'verified_by_user_id' => $staff?->id,
                'verified_channel_id' => $via === CreatorClaimVerifiedVia::DiscordConnection
                    ? $verifiedChannel?->id
                    : null,
            ])->save();

            $this->closeCompetingClaims((string) $creator->id, (string) $locked->id);

            return $locked->fresh(['creator.members', 'channel.platform', 'user']) ?? $locked;
        });
    }

    /**
     * Keep losing claims as audit log; mark cancelled + superseded.
     */
    private function closeCompetingClaims(string $creatorId, string $exceptClaimId): void
    {
        CreatorClaim::query()
            ->where('creator_id', $creatorId)
            ->where('status', CreatorClaimStatus::Pending)
            ->whereKeyNot($exceptClaimId)
            ->update([
                'status' => CreatorClaimStatus::Cancelled->value,
                'closed_reason' => CreatorClaimClosedReason::Superseded->value,
            ]);
    }

    private function closeClaim(
        CreatorClaim $claim,
        CreatorClaimStatus $status,
        CreatorClaimClosedReason $reason,
    ): void {
        $claim->forceFill([
            'status' => $status,
            'closed_reason' => $reason,
        ])->save();
    }

    private function lockPendingClaim(string $claimId): CreatorClaim
    {
        $claim = CreatorClaim::query()
            ->with(['channel.platform', 'creator', 'user'])
            ->findOrFail($claimId);

        if ($claim->status !== CreatorClaimStatus::Pending) {
            throw new VtualException(__('api/vtual.claim.not_pending'), 422);
        }

        return $claim;
    }

    private function assertClaimant(CreatorClaim $claim, User $actor): void
    {
        if ((string) $claim->user_id !== (string) $actor->id) {
            throw new VtualException(__('api/vtual.policy.access_denied'), 403);
        }
    }

    private function assertNotExpired(CreatorClaim $claim): void
    {
        if ($claim->expires_at !== null && $claim->expires_at->isPast()) {
            $this->closeClaim($claim, CreatorClaimStatus::Expired, CreatorClaimClosedReason::Expired);
            throw new VtualException(__('api/vtual.claim.expired'), 422);
        }
    }

    private function assertRateLimit(CreatorClaim $claim): void
    {
        $maxAttempts = $this->maxAttempts();
        if ((int) $claim->attempt_count >= $maxAttempts) {
            throw new VtualException(__('api/vtual.claim.max_attempts'), 422);
        }

        $cooldown = max(0, (int) setting_config('vtual.claim.verify_cooldown_seconds', 60));
        if ($cooldown > 0
            && $claim->last_attempt_at !== null
            && $claim->last_attempt_at->gt(now()->subSeconds($cooldown))) {
            throw new VtualException(__('api/vtual.claim.cooldown', [
                'seconds' => $cooldown,
            ]), 422);
        }
    }
}
