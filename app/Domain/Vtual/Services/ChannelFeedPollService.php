<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ContentFetchPoolSource;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Integrations\Dto\YoutubeFeedVideoEntryDto;
use App\Domain\Vtual\Integrations\Youtube\YoutubeChannelVideoFeedClient;
use App\Domain\Vtual\Models\Channel;
use Illuminate\Support\Carbon;

/**
 * Poll public YouTube Atom feeds and enqueue new/changed video ids for API hydrate.
 */
class ChannelFeedPollService
{
    public function __construct(
        private readonly YoutubeChannelVideoFeedClient $feedClient,
        private readonly ContentFetchPoolService $contentPool,
    ) {}

    /**
     * @return array{enqueued: int, skipped: int}
     */
    public function pollChannel(Channel $channel, bool $force = false): array
    {
        $channel->loadMissing('platform');
        if ($channel->status !== ChannelStatus::Active) {
            return ['enqueued' => 0, 'skipped' => 0];
        }

        $platformKey = $channel->platform?->key;
        if (! $platformKey instanceof PlatformKey) {
            $platformKey = PlatformKey::tryFrom((string) $channel->platform?->key);
        }
        if ($platformKey !== PlatformKey::Youtube) {
            return ['enqueued' => 0, 'skipped' => 0];
        }

        if ($channel->content_initial_backfill_at === null && ! $force) {
            return ['enqueued' => 0, 'skipped' => 0];
        }

        $externalId = trim((string) $channel->external_id);
        if ($externalId === '' || ! str_starts_with($externalId, 'UC')) {
            return ['enqueued' => 0, 'skipped' => 0];
        }

        if (! $force && ! $this->isDue($channel)) {
            return ['enqueued' => 0, 'skipped' => 0];
        }

        $entries = $this->feedClient->listVideos($externalId);
        $watermark = $channel->feed_last_entry_updated_at;
        $enqueued = 0;
        $skipped = 0;
        $maxUpdated = $watermark;

        foreach ($entries as $entry) {
            if (! $this->shouldEnqueue($entry, $watermark, $force)) {
                $skipped++;

                continue;
            }

            $this->contentPool->enqueue($channel, $entry->videoId, ContentFetchPoolSource::FeedPoll);
            $enqueued++;

            if ($entry->updatedAt !== null && ($maxUpdated === null || $entry->updatedAt->gt($maxUpdated))) {
                $maxUpdated = $entry->updatedAt;
            }
        }

        $channel->forceFill([
            'feed_last_polled_at' => now(),
            'feed_last_entry_updated_at' => $maxUpdated ?? $watermark ?? now(),
        ])->save();

        return ['enqueued' => $enqueued, 'skipped' => $skipped];
    }

    /**
     * @return array{channels: int, enqueued: int, skipped: int}
     */
    public function pollDueChannels(?string $onlyChannelId = null, bool $force = false): array
    {
        $query = Channel::query()
            ->with('platform')
            ->where('status', ChannelStatus::Active)
            ->whereNotNull('content_initial_backfill_at')
            ->whereHas('platform', fn ($q) => $q->where('key', PlatformKey::Youtube->value)->where('enabled', true))
            ->where('external_id', 'like', 'UC%');

        if ($onlyChannelId !== null && $onlyChannelId !== '') {
            $query->whereKey($onlyChannelId);
        }

        $channels = 0;
        $enqueued = 0;
        $skipped = 0;

        foreach ($query->cursor() as $channel) {
            if (! $force && ! $this->isDue($channel)) {
                continue;
            }

            $channels++;
            $result = $this->pollChannel($channel, $force);
            $enqueued += $result['enqueued'];
            $skipped += $result['skipped'];
        }

        return [
            'channels' => $channels,
            'enqueued' => $enqueued,
            'skipped' => $skipped,
        ];
    }

    private function isDue(Channel $channel): bool
    {
        $interval = max(1, (int) setting_config('vtual.feed_poll.interval_minutes', 15));
        $last = $channel->feed_last_polled_at;

        return $last === null || $last->lte(now()->subMinutes($interval));
    }

    private function shouldEnqueue(YoutubeFeedVideoEntryDto $entry, ?Carbon $watermark, bool $force): bool
    {
        if ($force) {
            return true;
        }

        if ($watermark === null) {
            return true;
        }

        if ($entry->updatedAt !== null && $entry->updatedAt->gt($watermark)) {
            return true;
        }

        return false;
    }
}
