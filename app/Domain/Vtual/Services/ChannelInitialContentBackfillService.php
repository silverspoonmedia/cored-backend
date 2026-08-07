<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Integrations\Youtube\YoutubeChannelVideoFeedClient;
use App\Domain\Vtual\Models\Channel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One-time YouTube content backfill on channel activation (playlist + videos.list).
 */
class ChannelInitialContentBackfillService
{
    public function __construct(
        private readonly ChannelContentFetchService $contentFetch,
        private readonly YoutubeChannelVideoFeedClient $feedClient,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     reason?: string,
     *     listed?: int,
     *     fetched?: int,
     *     upserted?: int,
     * }
     */
    public function run(Channel $channel): array
    {
        $channel = $channel->fresh(['platform']) ?? $channel;
        $channel->loadMissing('platform');

        if ($channel->content_initial_backfill_at !== null) {
            return ['status' => 'skipped', 'reason' => 'already_backfilled'];
        }

        if ($channel->status !== ChannelStatus::Active) {
            return ['status' => 'skipped', 'reason' => 'channel_not_active'];
        }

        $platformKey = $this->platformKey($channel);
        if ($platformKey !== PlatformKey::Youtube) {
            return ['status' => 'skipped', 'reason' => 'platform_not_youtube'];
        }

        $externalId = trim((string) $channel->external_id);
        if ($externalId === '' || ! str_starts_with($externalId, 'UC')) {
            return ['status' => 'skipped', 'reason' => 'invalid_external_id'];
        }

        $limit = max(1, (int) config('vtual.http.batch.playlist_items', 50));

        try {
            $result = $this->contentFetch->fetchAndPersist($channel, $limit);
        } catch (Throwable $e) {
            Log::warning('vtual.channel_initial_backfill_failed', [
                'channel_id' => $channel->id,
                'external_id' => $externalId,
                'message' => $e->getMessage(),
            ]);

            return ['status' => 'failed', 'reason' => $e->getMessage()];
        }

        $maxUpdated = $this->resolveFeedWatermark($externalId);

        $channel->forceFill([
            'content_initial_backfill_at' => now(),
            'feed_last_entry_updated_at' => $maxUpdated,
            'feed_last_polled_at' => now(),
        ])->save();

        return array_merge(['status' => 'completed'], $result);
    }

    private function resolveFeedWatermark(string $channelExternalId): Carbon
    {
        try {
            $entries = $this->feedClient->listVideos($channelExternalId);
            $maxUpdated = null;

            foreach ($entries as $entry) {
                if ($entry->updatedAt !== null && ($maxUpdated === null || $entry->updatedAt->gt($maxUpdated))) {
                    $maxUpdated = $entry->updatedAt;
                }
            }

            return $maxUpdated ?? now();
        } catch (Throwable $e) {
            Log::warning('vtual.channel_initial_backfill_feed_watermark_failed', [
                'external_id' => $channelExternalId,
                'message' => $e->getMessage(),
            ]);

            return now();
        }
    }

    private function platformKey(Channel $channel): PlatformKey
    {
        $key = $channel->platform?->key;
        if ($key instanceof PlatformKey) {
            return $key;
        }

        return PlatformKey::from((string) $key);
    }
}
