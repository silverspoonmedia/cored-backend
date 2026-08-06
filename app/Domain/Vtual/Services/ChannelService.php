<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelFetchPoolReason;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Dto\PlatformChannelDto;
use App\Domain\Vtual\Integrations\Support\ChannelProfileFingerprint;
use App\Domain\Vtual\Integrations\Support\ChannelProfilePlatformGuard;
use App\Domain\Vtual\Integrations\Support\ChannelProfileResolver;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Platform;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChannelService
{
    public function __construct(
        private readonly CreatorAccessService $creatorAccess,
        private readonly ChannelProfileResolver $profiles,
        private readonly ChannelFetchPoolService $fetchPool,
        private readonly ChannelRegistrationFlushService $registrationFlush,
    ) {}

    /**
     * Non-archived channels for manage/list UI.
     *
     * @return Collection<int, Channel>
     */
    public function listForCreator(User $actor, string $creatorId): Collection
    {
        $creator = $this->creatorAccess->findOrFail($creatorId);
        $this->creatorAccess->assertCanView($creator, $actor);

        return Channel::query()
            ->where('creator_id', $creator->id)
            ->where('status', '!=', ChannelStatus::Archived)
            ->with('platform')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Archived channels only (restore UI).
     *
     * @return Collection<int, Channel>
     */
    public function listArchivedForCreator(User $actor, string $creatorId): Collection
    {
        $creator = $this->creatorAccess->findOrFail($creatorId);
        $this->creatorAccess->assertCanView($creator, $actor);

        return Channel::query()
            ->where('creator_id', $creator->id)
            ->where('status', ChannelStatus::Archived)
            ->with('platform')
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * Staff-only instant bind: resolve platform profile → insert active channel.
     * Used to seed proof channels on orphans (and any profile staff may mutate).
     * Non-staff product path must use ChannelClaimService (bio token).
     *
     * @param  array{platform_key: string, profile: string}  $data
     */
    public function seedForStaff(User $actor, string $creatorId, array $data): Channel
    {
        if (! $actor->can('manage any vtual resource')) {
            throw new VtualException(__('api/vtual.policy.denied'), 403);
        }

        $creator = $this->creatorAccess->findOrFail($creatorId);
        // manage any already mutates; still load creator for existence / soft-delete.
        $this->creatorAccess->assertCanMutate($creator, $actor);

        $platform = Platform::query()->where('key', $data['platform_key'] ?? null)->first();
        if ($platform === null) {
            throw new VtualException(__('api/vtual.channel.platform_unknown'), 422);
        }
        if (! $platform->isEnabled()) {
            throw new VtualException(__('api/vtual.channel.platform_disabled'), 422);
        }

        $profile = trim((string) ($data['profile'] ?? ''));
        if ($profile === '') {
            throw new VtualException(__('api/vtual.channel.profile_required'), 422);
        }

        $platformKey = $this->platformKey($platform);
        ChannelProfilePlatformGuard::assertMatchesPlatform($platformKey, $profile);

        $dto = $this->profiles->resolve($platformKey, $profile);

        return $this->activateFromDto((string) $creator->id, $platform, $dto);
    }

    /**
     * Staff-only deferred bind: store raw profile for batch resolve (no API call in this request).
     *
     * @param  array{platform_key: string, profile: string}  $data
     */
    public function registerPendingForStaff(User $actor, string $creatorId, array $data): Channel
    {
        if (! $actor->can('manage any vtual resource')) {
            throw new VtualException(__('api/vtual.policy.denied'), 403);
        }

        $creator = $this->creatorAccess->findOrFail($creatorId);
        $this->creatorAccess->assertCanMutate($creator, $actor);

        $platform = Platform::query()->where('key', $data['platform_key'] ?? null)->first();
        if ($platform === null) {
            throw new VtualException(__('api/vtual.channel.platform_unknown'), 422);
        }
        if (! $platform->isEnabled()) {
            throw new VtualException(__('api/vtual.channel.platform_disabled'), 422);
        }

        $profile = trim((string) ($data['profile'] ?? ''));
        if ($profile === '') {
            throw new VtualException(__('api/vtual.channel.profile_required'), 422);
        }

        $platformKey = $this->platformKey($platform);
        ChannelProfilePlatformGuard::assertMatchesPlatform($platformKey, $profile);

        try {
            $fingerprint = ChannelProfileFingerprint::from($platformKey, $profile);
        } catch (VtualException $e) {
            throw ValidationException::withMessages([
                'profile' => [$e->getMessage()],
            ]);
        }

        $duplicate = Channel::query()
            ->where('creator_id', $creator->id)
            ->where('platform_id', $platform->id)
            ->where('profile_fingerprint', $fingerprint)
            ->where('status', '!=', ChannelStatus::Archived)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'profile' => [__('api/vtual.channel.duplicate_on_creator')],
            ]);
        }

        $channelId = (string) Str::uuid();

        $channel = Channel::query()->create([
            'id' => $channelId,
            'creator_id' => $creator->id,
            'platform_id' => $platform->id,
            'external_id' => 'pending:'.$channelId,
            'profile_input' => $profile,
            'profile_fingerprint' => $fingerprint,
            'status' => ChannelStatus::Pending,
        ]);

        $threshold = max(1, (int) setting_config('vtual.channel_pool.threshold', 50));
        $pending = Channel::query()
            ->where('platform_id', $platform->id)
            ->where('status', ChannelStatus::Pending)
            ->whereNotNull('profile_input')
            ->count();

        if ($pending >= $threshold) {
            $this->registrationFlush->flush($this->platformKey($platform), force: false);
        }

        return $channel->load('platform');
    }

    /**
     * Promote a pending registration row after batch profile resolve.
     */
    public function activatePendingFromDto(
        Channel $channel,
        Platform $platform,
        PlatformChannelDto $dto,
    ): Channel {
        $exists = Channel::query()
            ->where('platform_id', $platform->id)
            ->where('external_id', $dto->externalId)
            ->where('id', '!=', $channel->id)
            ->exists();

        if ($exists) {
            throw new VtualException(__('api/vtual.channel.external_id_taken'), 422);
        }

        $channel->forceFill(array_merge(
            [
                'external_id' => $dto->externalId,
                'profile_input' => null,
                'resolve_error' => null,
            ],
            $this->attributesFromDto($dto),
        ))->save();

        return $channel->refresh()->load('platform');
    }

    /**
     * Activate a channel after ownership proof (ChannelClaimService verify / Accept Anyway)
     * or staff seedForStaff. Not a general public “bind now” for non-staff.
     */
    public function activateFromDto(
        string $creatorId,
        Platform $platform,
        PlatformChannelDto $dto,
    ): Channel {
        $exists = Channel::query()
            ->where('platform_id', $platform->id)
            ->where('external_id', $dto->externalId)
            ->exists();

        if ($exists) {
            throw new VtualException(__('api/vtual.channel.external_id_taken'), 422);
        }

        $channel = Channel::query()->create(array_merge(
            [
                'creator_id' => $creatorId,
                'platform_id' => $platform->id,
                'external_id' => $dto->externalId,
            ],
            $this->attributesFromDto($dto),
        ));

        return $channel->load('platform');
    }

    /**
     * Queue profile re-fetch by stored external_id (channel fetch pool).
     * Snapshot updates after pool flush (size 50 or max wait), not in this request.
     */
    public function refresh(User $actor, string $channelId): Channel
    {
        $channel = Channel::query()->with(['platform', 'creator'])->findOrFail($channelId);
        $this->creatorAccess->assertCanMutate($channel->creator, $actor);

        if ($channel->status === ChannelStatus::Archived) {
            throw new VtualException(__('api/vtual.channel.archived'), 422);
        }

        $platform = $channel->platform;
        if ($platform === null || ! $platform->isEnabled()) {
            throw new VtualException(__('api/vtual.channel.platform_disabled'), 422);
        }

        $this->fetchPool->enqueue(
            $platform,
            (string) $channel->external_id,
            (string) $channel->id,
            ChannelFetchPoolReason::Refresh,
        );

        return $channel->load('platform');
    }

    /**
     * Soft-remove channel from manage UI (status archived; row kept).
     */
    public function archive(User $actor, string $channelId): Channel
    {
        $channel = Channel::query()->with(['platform', 'creator'])->findOrFail($channelId);
        $this->creatorAccess->assertCanMutate($channel->creator, $actor);

        $channel->forceFill([
            'status' => ChannelStatus::Archived,
        ])->save();

        return $channel->refresh()->load('platform');
    }

    /**
     * Restore archived channel to active (no platform re-fetch; use refresh after).
     */
    public function restore(User $actor, string $channelId): Channel
    {
        $channel = Channel::query()->with(['platform', 'creator'])->findOrFail($channelId);
        $this->creatorAccess->assertCanMutate($channel->creator, $actor);

        if ($channel->status !== ChannelStatus::Archived) {
            throw new VtualException(__('api/vtual.channel.not_archived'), 422);
        }

        $channel->forceFill([
            'status' => ChannelStatus::Active,
        ])->save();

        return $channel->refresh()->load('platform');
    }

    public function show(User $actor, string $channelId): Channel
    {
        $channel = Channel::query()->with(['platform', 'creator'])->findOrFail($channelId);
        $this->creatorAccess->assertCanView($channel->creator, $actor);

        return $channel;
    }

    /**
     * Shared snapshot fields from platform resolve (create + refresh).
     *
     * @return array<string, mixed>
     */
    private function attributesFromDto(PlatformChannelDto $dto): array
    {
        return [
            'handle' => $dto->handle,
            'display_name' => $dto->displayName,
            'description' => $dto->description,
            'avatar_url' => $dto->thumbnailUrl,
            'banner_url' => $dto->bannerUrl,
            'url' => $dto->url,
            'platform_created_at' => $dto->platformCreatedAt,
            'status' => ChannelStatus::Active,
            'last_synced_at' => now(),
        ];
    }

    private function platformKey(Platform $platform): PlatformKey
    {
        $key = $platform->key;
        if ($key instanceof PlatformKey) {
            return $key;
        }

        return PlatformKey::from((string) $key);
    }
}
