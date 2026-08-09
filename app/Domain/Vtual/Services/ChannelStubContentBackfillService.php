<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Content;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Batch-hydrate stub contents (feed/WebSub only) after channel restore.
 */
class ChannelStubContentBackfillService
{
    public function __construct(
        private readonly ChannelContentFetchService $contentFetch,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     reason?: string,
     *     stubs?: int,
     *     requested?: int,
     *     fetched?: int,
     *     upserted?: int,
     * }
     */
    public function run(Channel $channel): array
    {
        $channel = $channel->fresh(['platform']) ?? $channel;
        $channel->loadMissing('platform');

        if ($channel->status !== ChannelStatus::Active) {
            return ['status' => 'skipped', 'reason' => 'channel_not_active'];
        }

        $platformKey = $this->platformKey($channel);
        if ($platformKey !== PlatformKey::Youtube) {
            return ['status' => 'skipped', 'reason' => 'platform_not_youtube'];
        }

        $stubIds = Content::query()
            ->where('channel_id', $channel->id)
            ->whereNull('hydrated_at')
            ->orderBy('created_at')
            ->pluck('external_id')
            ->map(fn (mixed $id): string => trim((string) $id))
            ->filter(fn (string $id): bool => $id !== '')
            ->values()
            ->all();

        if ($stubIds === []) {
            return ['status' => 'skipped', 'reason' => 'no_stubs'];
        }

        $batchMax = max(1, (int) setting_config('vtual.http.batch.videos', 50));
        $requested = 0;
        $fetched = 0;
        $upserted = 0;

        try {
            foreach (array_chunk($stubIds, $batchMax) as $chunk) {
                $result = $this->contentFetch->fetchVideosAndPersist($channel, $chunk);
                $requested += $result['requested'];
                $fetched += $result['fetched'];
                $upserted += $result['upserted'];
            }
        } catch (Throwable $e) {
            Log::warning('vtual.channel_stub_backfill_failed', [
                'channel_id' => $channel->id,
                'message' => $e->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'reason' => $e->getMessage(),
                'stubs' => count($stubIds),
                'requested' => $requested,
                'fetched' => $fetched,
                'upserted' => $upserted,
            ];
        }

        return [
            'status' => 'completed',
            'stubs' => count($stubIds),
            'requested' => $requested,
            'fetched' => $fetched,
            'upserted' => $upserted,
        ];
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
