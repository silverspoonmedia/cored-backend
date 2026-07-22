<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Dto\PlatformChannelDto;
use App\Domain\Vtual\Integrations\Support\ChannelProfileResolver;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Platform;
use Illuminate\Database\Eloquent\Collection;

class ChannelService
{
    public function __construct(
        private readonly CreatorAccessService $creatorAccess,
        private readonly ChannelProfileResolver $profiles,
    ) {}

    /**
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
     * Activate a channel after ownership proof (ChannelClaimService verify / Accept Anyway).
     * Not a public “bind now” path — product add-channel must go through bio claim first.
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
     * Re-fetch profile by stored external_id; update snapshot fields and mark active (verify).
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

        $dto = $this->profiles->resolveByExternalId(
            $this->platformKey($platform),
            (string) $channel->external_id,
        );

        $channel->forceFill($this->attributesFromDto($dto))->save();

        return $channel->refresh()->load('platform');
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
