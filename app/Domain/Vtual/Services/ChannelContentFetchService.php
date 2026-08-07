<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Dto\PlatformVideoDto;
use App\Domain\Vtual\Integrations\PlatformApiGateway;
use App\Domain\Vtual\Models\Channel;

/**
 * Pull recent YouTube uploads for a channel and persist to vtual_contents.
 */
class ChannelContentFetchService
{
    public function __construct(
        private readonly PlatformApiGateway $gateway,
        private readonly ContentService $contents,
        private readonly PlatformVideoDtoMapper $mapper,
    ) {}

    /**
     * @return array{listed: int, fetched: int, upserted: int}
     */
    public function fetchAndPersist(Channel $channel, int $limit = 50): array
    {
        $channel = $this->assertFetchableYoutubeChannel($channel);

        $max = (int) config('vtual.http.batch.playlist_items', 50);
        $limit = min(max(1, $limit), $max);

        $videoIds = $this->gateway
            ->listChannelVideos(PlatformKey::Youtube, (string) $channel->external_id, $limit)
            ->values()
            ->all();

        $listed = count($videoIds);
        if ($listed === 0) {
            return ['listed' => 0, 'fetched' => 0, 'upserted' => 0];
        }

        return array_merge(
            ['listed' => $listed],
            $this->persistVideos($channel, $videoIds),
        );
    }

    /**
     * Fetch explicit video ids (live fallback / WebSub recovery).
     *
     * @param  list<string>  $videoIds
     * @return array{requested: int, fetched: int, upserted: int}
     */
    public function fetchVideosAndPersist(Channel $channel, array $videoIds): array
    {
        $channel = $this->assertFetchableYoutubeChannel($channel);

        $ids = collect($videoIds)
            ->map(fn (mixed $id): string => trim((string) $id))
            ->filter(fn (string $id): bool => $id !== '')
            ->unique()
            ->values()
            ->all();

        $requested = count($ids);
        if ($requested === 0) {
            return ['requested' => 0, 'fetched' => 0, 'upserted' => 0];
        }

        return array_merge(
            ['requested' => $requested],
            $this->persistVideos($channel, $ids),
        );
    }

    /**
     * Discover currently live broadcast video ids for a channel.
     *
     * @return list<string>
     */
    public function discoverLiveVideoIds(Channel $channel, int $limit = 5): array
    {
        $channel = $this->assertFetchableYoutubeChannel($channel);

        return $this->gateway
            ->searchChannelVideos(PlatformKey::Youtube, (string) $channel->external_id, 'live', $limit)
            ->values()
            ->all();
    }

    private function assertFetchableYoutubeChannel(Channel $channel): Channel
    {
        $channel->loadMissing('platform');

        $platformKey = $this->resolvePlatformKey($channel);
        if ($platformKey !== PlatformKey::Youtube) {
            throw new VtualException(__('api/vtual.content.fetch_platform_unsupported', [
                'platform' => $platformKey->value,
            ]), 422);
        }

        if ($channel->status !== ChannelStatus::Active) {
            throw new VtualException(__('api/vtual.content.fetch_channel_not_active'), 422);
        }

        $externalId = trim((string) $channel->external_id);
        if ($externalId === '' || ! str_starts_with($externalId, 'UC')) {
            throw new VtualException(__('api/vtual.integrations.youtube_channel_id_required'), 422);
        }

        return $channel;
    }

    /**
     * @param  list<string>  $videoIds
     * @return array{fetched: int, upserted: int}
     */
    private function persistVideos(Channel $channel, array $videoIds): array
    {
        $videos = $this->gateway->fetchVideos(PlatformKey::Youtube, $videoIds);
        $fetched = $videos->count();
        $channelExternalId = trim((string) $channel->external_id);

        $upserted = 0;
        foreach ($videos as $video) {
            $this->assertVideoBelongsToChannel($video, $channelExternalId);
            $this->contents->upsertFromPrimary($channel, $this->mapper->toUpsertPayload($video));
            $upserted++;
        }

        return [
            'fetched' => $fetched,
            'upserted' => $upserted,
        ];
    }

    private function assertVideoBelongsToChannel(PlatformVideoDto $video, string $channelExternalId): void
    {
        if ($video->channelExternalId === null || $video->channelExternalId === $channelExternalId) {
            return;
        }

        throw new VtualException(__('api/vtual.content.fetch_video_channel_mismatch', [
            'video' => $video->externalId,
            'channel' => $channelExternalId,
        ]), 422);
    }

    private function resolvePlatformKey(Channel $channel): PlatformKey
    {
        $key = $channel->platform?->key;
        if ($key instanceof PlatformKey) {
            return $key;
        }

        return PlatformKey::from((string) $key);
    }
}
