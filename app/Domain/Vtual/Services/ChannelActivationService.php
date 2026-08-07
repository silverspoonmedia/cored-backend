<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Dto\PlatformChannelDto;
use App\Domain\Vtual\Jobs\RunChannelInitialContentBackfillJob;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Platform;

/**
 * Single entry for pending → active promotion and YouTube post-activation hooks.
 */
class ChannelActivationService
{
    public function __construct(
        private readonly YoutubeWebSubSubscriptionService $websub,
    ) {}

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

        $channel = $channel->refresh()->load('platform');
        $this->applyYoutubeActivationHooks($channel);

        return $channel;
    }

    /**
     * WebSub subscribe + initial content backfill job for active YouTube channels.
     */
    public function applyYoutubeActivationHooks(Channel $channel): void
    {
        $channel->loadMissing('platform');
        $platform = $channel->platform;
        if ($platform === null) {
            return;
        }

        if ($this->platformKey($platform) !== PlatformKey::Youtube) {
            return;
        }

        $this->websub->subscribe($channel);
        RunChannelInitialContentBackfillJob::dispatch((string) $channel->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesFromDto(PlatformChannelDto $dto): array
    {
        return [
            'handle' => $dto->handle,
            'display_name' => $dto->displayName,
            'description' => $dto->description,
            'avatar_url' => $dto->thumbnailUrl,
            'banner_url' => $dto->bannerUrl,
            'url' => $dto->url,
            'platform_created_at' => $dto->platformCreatedAt,
            'subscriber_count' => $dto->subscriberCount,
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
