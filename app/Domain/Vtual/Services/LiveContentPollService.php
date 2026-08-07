<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Integrations\PlatformApiGateway;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Content;

/**
 * Poll API for live contents: CCV snapshots and ended detection.
 */
class LiveContentPollService
{
    public function __construct(
        private readonly PlatformApiGateway $gateway,
        private readonly PlatformVideoDtoMapper $mapper,
        private readonly ContentService $contents,
    ) {}

    /**
     * @return array{polled: int, updated: int, ended: int}
     */
    public function poll(): array
    {
        $liveContents = Content::query()
            ->with(['channel.platform'])
            ->where('type', ContentType::Live)
            ->where('status', ContentStatus::Live)
            ->whereHas('channel', fn ($q) => $q->where('status', ChannelStatus::Active))
            ->get();

        if ($liveContents->isEmpty()) {
            return ['polled' => 0, 'updated' => 0, 'ended' => 0];
        }

        $byChannel = $liveContents->groupBy('channel_id');
        $polled = 0;
        $updated = 0;
        $ended = 0;

        foreach ($byChannel as $channelId => $items) {
            /** @var Channel|null $channel */
            $channel = Channel::query()->with('platform')->find($channelId);
            if ($channel === null || $channel->status !== ChannelStatus::Active) {
                continue;
            }

            $platformKey = $channel->platform?->key;
            if (! $platformKey instanceof PlatformKey) {
                $platformKey = PlatformKey::tryFrom((string) $channel->platform?->key);
            }
            if ($platformKey !== PlatformKey::Youtube) {
                continue;
            }

            $videoIds = $items->pluck('external_id')->filter()->unique()->values()->all();
            $batchMax = max(1, (int) setting_config('vtual.http.batch.videos', 50));

            foreach (array_chunk($videoIds, $batchMax) as $chunk) {
                $videos = $this->gateway->fetchVideos(PlatformKey::Youtube, $chunk);
                $polled += count($chunk);

                foreach ($videos as $video) {
                    $channelExternalId = trim((string) $channel->external_id);
                    if ($video->channelExternalId !== null && $video->channelExternalId !== $channelExternalId) {
                        continue;
                    }

                    $this->contents->upsertFromPrimary($channel, $this->mapper->toUpsertPayload($video));
                    $updated++;

                    if ($video->status === 'ended') {
                        $ended++;
                    }
                }
            }
        }

        return [
            'polled' => $polled,
            'updated' => $updated,
            'ended' => $ended,
        ];
    }
}
