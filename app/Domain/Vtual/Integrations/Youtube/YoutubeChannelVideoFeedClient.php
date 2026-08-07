<?php

namespace App\Domain\Vtual\Integrations\Youtube;

use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Dto\YoutubeFeedVideoEntryDto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Public YouTube channel video Atom feed (no API quota).
 */
class YoutubeChannelVideoFeedClient
{
    private const string FEED_BASE = 'https://www.youtube.com/feeds/videos.xml';

    public function __construct(
        private readonly YoutubeAtomFeedParser $parser,
    ) {}

    /**
     * @return Collection<int, YoutubeFeedVideoEntryDto>
     */
    public function listVideos(string $channelExternalId): Collection
    {
        $channelId = trim($channelExternalId);
        if ($channelId === '' || ! str_starts_with($channelId, 'UC')) {
            throw new VtualException(__('api/vtual.integrations.youtube_channel_id_required'), 422);
        }

        $timeout = max(5, (int) config('vtual.http.timeout', 20));
        $response = Http::timeout($timeout)
            ->accept('application/atom+xml, application/xml, text/xml')
            ->get(self::FEED_BASE, ['channel_id' => $channelId]);

        if (! $response->successful()) {
            throw new VtualException(__('api/vtual.integrations.feed_fetch_failed', [
                'status' => $response->status(),
            ]), 502);
        }

        $body = $response->body();
        if (! is_string($body) || trim($body) === '') {
            return collect();
        }

        return $this->parser->parseEntries($body, throwOnInvalid: true);
    }

    public function topicUrl(string $channelExternalId): string
    {
        $channelId = trim($channelExternalId);

        return self::FEED_BASE.'?channel_id='.$channelId;
    }
}
