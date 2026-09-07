<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\PlatformApiGateway;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Content;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Poll API for live contents: CCV snapshots and ended detection.
 * YouTube branch polls videos.list per live row; Twitch branch batches
 * /streams by user id — a stream missing from the response ends its rows
 * (backstop for missed stream.offline webhooks).
 */
class LiveContentPollService
{
    public function __construct(
        private readonly PlatformApiGateway $gateway,
        private readonly PlatformVideoDtoMapper $mapper,
        private readonly ContentService $contents,
        private readonly TwitchLiveStateService $liveState,
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

        $summary = ['polled' => 0, 'updated' => 0, 'ended' => 0];

        foreach ([
            PlatformKey::Youtube->value => $liveContents->filter(fn (Content $c) => $this->platformKeyOf($c) === PlatformKey::Youtube),
            PlatformKey::Twitch->value => $liveContents->filter(fn (Content $c) => $this->platformKeyOf($c) === PlatformKey::Twitch),
        ] as $platformKeyValue => $items) {
            if ($items->isEmpty()) {
                continue;
            }

            $platformKey = PlatformKey::from($platformKeyValue);
            $result = $platformKey === PlatformKey::Twitch
                ? $this->pollTwitch($items)
                : $this->pollYoutube($items);

            $summary['polled'] += $result['polled'];
            $summary['updated'] += $result['updated'];
            $summary['ended'] += $result['ended'];
        }

        return $summary;
    }

    /**
     * YouTube: per-channel videos.list poll of the live external ids.
     *
     * @param  Collection<int, Content>  $items
     * @return array{polled: int, updated: int, ended: int}
     */
    private function pollYoutube(Collection $items): array
    {
        $polled = 0;
        $updated = 0;
        $ended = 0;

        $byChannel = $items->groupBy('channel_id');

        foreach ($byChannel as $channelId => $channelItems) {
            /** @var Channel|null $channel */
            $channel = Channel::query()->with('platform')->find($channelId);
            if ($channel === null || $channel->status !== ChannelStatus::Active) {
                continue;
            }

            $videoIds = $channelItems->pluck('external_id')->filter()->unique()->values()->all();
            $batchMax = max(1, (int) setting_config('vtual.http.batch.videos', 50));

            foreach (array_chunk($videoIds, $batchMax) as $chunk) {
                try {
                    $videos = $this->gateway->fetchVideos(PlatformKey::Youtube, $chunk);
                } catch (VtualException $e) {
                    Log::warning('vtual.live_poll_failed', [
                        'platform' => PlatformKey::Youtube->value,
                        'channel_id' => (string) $channel->id,
                        'error' => mb_substr($e->getMessage(), 0, 200),
                    ]);
                    continue;
                }

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

        return ['polled' => $polled, 'updated' => $updated, 'ended' => $ended];
    }

    /**
     * Twitch: batch /streams over every channel with live rows (≤100 user ids
     * per call). Present streams refresh the live row; missing streams end it.
     *
     * @param  Collection<int, Content>  $items
     * @return array{polled: int, updated: int, ended: int}
     */
    private function pollTwitch(Collection $items): array
    {
        $polled = 0;
        $updated = 0;
        $ended = 0;

        /** @var Collection<int, Channel> $channels */
        $channels = $items->pluck('channel')->filter()->unique('id')->values();
        $userIds = $channels->pluck('external_id')->map(fn ($v) => trim((string) $v))->filter()->unique()->values()->all();

        if ($userIds === []) {
            return ['polled' => 0, 'updated' => 0, 'ended' => 0];
        }

        try {
            /** @var Collection<int, \App\Domain\Vtual\Integrations\Dto\PlatformStreamDto> $streams */
            $streams = $this->gateway->fetchStreamsByUserIds(PlatformKey::Twitch, $userIds);
        } catch (VtualException $e) {
            Log::warning('vtual.live_poll_failed', [
                'platform' => PlatformKey::Twitch->value,
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);

            return ['polled' => 0, 'updated' => 0, 'ended' => 0];
        }

        $polled = count($userIds);
        $byUser = $streams->keyBy(fn ($s) => (string) $s->userExternalId);

        foreach ($channels as $channel) {
            $stream = $byUser->get(trim((string) $channel->external_id));

            if ($stream === null) {
                $ended += $this->liveState->markOffline($channel);

                continue;
            }

            $this->liveState->markOnline($channel, $stream);
            $updated++;
        }

        return ['polled' => $polled, 'updated' => $updated, 'ended' => $ended];
    }

    private function platformKeyOf(Content $content): ?PlatformKey
    {
        $key = $content->channel?->platform?->key;

        if ($key instanceof PlatformKey) {
            return $key;
        }

        return PlatformKey::tryFrom((string) $key);
    }
}
