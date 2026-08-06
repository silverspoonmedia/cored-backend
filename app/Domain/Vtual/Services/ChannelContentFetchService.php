<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\VtualException;
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

        $max = (int) config('vtual.http.batch.playlist_items', 50);
        $limit = min(max(1, $limit), $max);

        $videoIds = $this->gateway
            ->listChannelVideos(PlatformKey::Youtube, $externalId, $limit)
            ->values()
            ->all();

        $listed = count($videoIds);
        if ($listed === 0) {
            return ['listed' => 0, 'fetched' => 0, 'upserted' => 0];
        }

        $videos = $this->gateway->fetchVideos(PlatformKey::Youtube, $videoIds);
        $fetched = $videos->count();

        $upserted = 0;
        foreach ($videos as $video) {
            $this->contents->upsertFromPrimary($channel, $this->mapper->toUpsertPayload($video));
            $upserted++;
        }

        return [
            'listed' => $listed,
            'fetched' => $fetched,
            'upserted' => $upserted,
        ];
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
