<?php

namespace App\Domain\Vtual\Integrations\Youtube;

use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\PlatformApiException;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Concerns\UsesPlatformCredential;
use App\Domain\Vtual\Integrations\Contracts\FetchesChannels;
use App\Domain\Vtual\Integrations\Contracts\FetchesVideos;
use App\Domain\Vtual\Integrations\Contracts\PlatformClient;
use App\Domain\Vtual\Integrations\Contracts\ResolvesChannel;
use App\Domain\Vtual\Integrations\Dto\PlatformChannelDto;
use App\Domain\Vtual\Integrations\Dto\PlatformVideoDto;
use App\Domain\Vtual\Integrations\Dto\YoutubeParts;
use App\Domain\Vtual\Integrations\Enums\PlatformApiOperation;
use App\Domain\Vtual\Integrations\Support\BatchIdChunker;
use App\Domain\Vtual\Integrations\Support\PlatformHttp;
use App\Domain\Vtual\Services\PlatformCredentialService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class YoutubeDataApiClient implements PlatformClient, FetchesVideos, FetchesChannels, ResolvesChannel
{
    use UsesPlatformCredential;

    public function __construct(
        private readonly PlatformCredentialService $credentials,
    ) {}

    public function key(): PlatformKey
    {
        return PlatformKey::Youtube;
    }

    public function supports(PlatformApiOperation $operation): bool
    {
        return match ($operation) {
            PlatformApiOperation::FetchVideos,
            PlatformApiOperation::FetchChannels,
            PlatformApiOperation::ResolveChannel => true,
            PlatformApiOperation::FetchStreams,
            PlatformApiOperation::ListChannelVideos => false,
        };
    }

    public function fetchVideos(array $externalIds, ?array $parts = null): Collection
    {
        $max = (int) config('vtual.http.batch.videos', 50);
        $ids = BatchIdChunker::normalize($externalIds, $max, 'video ids');
        $partList = YoutubeParts::forVideos($parts);
        $apiKey = $this->apiKey();

        try {
            $response = PlatformHttp::get(
                $this->baseUrl().'/videos',
                [
                    'part' => implode(',', $partList),
                    'id' => implode(',', $ids),
                    'key' => $apiKey,
                ],
                platformKey: PlatformKey::Youtube->value,
                logContext: $this->httpLogContext('fetch_videos'),
            );
        } catch (PlatformApiException $e) {
            $this->handleAuthFailure($e, $this->credentials);
        }

        $this->credentials->markUsed($this->requireCredential());

        $items = $response->json('items') ?? [];
        if (! is_array($items)) {
            $items = [];
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): PlatformVideoDto => $this->mapVideo($item))
            ->values();
    }

    public function fetchChannels(array $externalIds, ?array $parts = null): Collection
    {
        $max = (int) config('vtual.http.batch.channels', 50);
        $ids = BatchIdChunker::normalize($externalIds, $max, 'channel ids');
        $partList = YoutubeParts::forChannels($parts);
        $apiKey = $this->apiKey();

        try {
            $response = PlatformHttp::get(
                $this->baseUrl().'/channels',
                [
                    'part' => implode(',', $partList),
                    'id' => implode(',', $ids),
                    'key' => $apiKey,
                ],
                platformKey: PlatformKey::Youtube->value,
                logContext: $this->httpLogContext('fetch_channels'),
            );
        } catch (PlatformApiException $e) {
            $this->handleAuthFailure($e, $this->credentials);
        }

        $this->credentials->markUsed($this->requireCredential());

        $items = $response->json('items') ?? [];
        if (! is_array($items)) {
            $items = [];
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): PlatformChannelDto => $this->mapChannel($item))
            ->values();
    }

    public function resolveChannel(string $handleOrLogin): ?PlatformChannelDto
    {
        $handle = ltrim(trim($handleOrLogin), '@');
        if ($handle === '') {
            throw new VtualException(__('api/vtual.integrations.handle_required'), 422);
        }

        $partList = YoutubeParts::forChannels(null);
        $apiKey = $this->apiKey();
        $query = [
            'part' => implode(',', $partList),
            'key' => $apiKey,
        ];

        // Prefer forHandle (modern); fall back not needed if API accepts handle without @.
        $query['forHandle'] = $handle;

        try {
            $response = PlatformHttp::get(
                $this->baseUrl().'/channels',
                $query,
                platformKey: PlatformKey::Youtube->value,
                logContext: $this->httpLogContext('resolve_channel'),
            );
        } catch (PlatformApiException $e) {
            $this->handleAuthFailure($e, $this->credentials);
        }

        $this->credentials->markUsed($this->requireCredential());

        $items = $response->json('items') ?? [];
        if (! is_array($items) || $items === [] || ! is_array($items[0] ?? null)) {
            return null;
        }

        return $this->mapChannel($items[0]);
    }

    private function apiKey(): string
    {
        $payload = $this->credentials->resolvePayload($this->requireCredential());
        $key = $payload['api_key'] ?? null;
        if (! is_string($key) || $key === '') {
            throw new VtualException(__('api/vtual.integrations.missing_api_key'), 422);
        }

        return $key;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('vtual.youtube.base_url', 'https://www.googleapis.com/youtube/v3'), '/');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function mapVideo(array $item): PlatformVideoDto
    {
        $id = (string) ($item['id'] ?? '');
        $snippet = is_array($item['snippet'] ?? null) ? $item['snippet'] : [];
        $content = is_array($item['contentDetails'] ?? null) ? $item['contentDetails'] : [];
        $stats = is_array($item['statistics'] ?? null) ? $item['statistics'] : [];
        $live = is_array($item['liveStreamingDetails'] ?? null) ? $item['liveStreamingDetails'] : [];
        $statusObj = is_array($item['status'] ?? null) ? $item['status'] : [];

        $thumbnails = is_array($snippet['thumbnails'] ?? null) ? $snippet['thumbnails'] : [];
        $thumbnail = $this->pickThumbnail($thumbnails);

        $liveBroadcastContent = is_string($snippet['liveBroadcastContent'] ?? null)
            ? $snippet['liveBroadcastContent']
            : null;

        $status = match ($liveBroadcastContent) {
            'live' => 'live',
            'upcoming' => 'upcoming',
            'none' => ($live !== [] ? 'ended' : 'uploaded'),
            default => 'unknown',
        };

        // Prefer liveStreamingDetails timestamps when present.
        if (isset($live['actualStartTime']) && ! isset($live['actualEndTime'])) {
            $status = 'live';
        } elseif (isset($live['actualEndTime'])) {
            $status = 'ended';
        } elseif (isset($live['scheduledStartTime']) && ! isset($live['actualStartTime'])) {
            $status = 'upcoming';
        }

        $privacy = is_string($statusObj['privacyStatus'] ?? null) ? $statusObj['privacyStatus'] : null;
        if ($privacy === 'private' && $status === 'unknown') {
            $status = 'unknown';
        }

        return new PlatformVideoDto(
            platform: PlatformKey::Youtube,
            externalId: $id,
            channelExternalId: isset($snippet['channelId']) ? (string) $snippet['channelId'] : null,
            title: isset($snippet['title']) ? (string) $snippet['title'] : null,
            description: isset($snippet['description']) ? (string) $snippet['description'] : null,
            thumbnailUrl: $thumbnail,
            url: $id !== '' ? 'https://www.youtube.com/watch?v='.$id : null,
            status: $status,
            publishedAt: $this->parseTime($snippet['publishedAt'] ?? null),
            startedAt: $this->parseTime($live['actualStartTime'] ?? $live['scheduledStartTime'] ?? null),
            endedAt: $this->parseTime($live['actualEndTime'] ?? null),
            durationSeconds: $this->parseIso8601Duration(isset($content['duration']) ? (string) $content['duration'] : null),
            viewCount: isset($stats['viewCount']) && is_numeric($stats['viewCount']) ? (int) $stats['viewCount'] : null,
            concurrentViewers: isset($live['concurrentViewers']) && is_numeric($live['concurrentViewers'])
                ? (int) $live['concurrentViewers']
                : null,
            raw: null,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function mapChannel(array $item): PlatformChannelDto
    {
        $id = (string) ($item['id'] ?? '');
        $snippet = is_array($item['snippet'] ?? null) ? $item['snippet'] : [];
        $thumbnails = is_array($snippet['thumbnails'] ?? null) ? $snippet['thumbnails'] : [];
        $customUrl = isset($snippet['customUrl']) ? ltrim((string) $snippet['customUrl'], '@') : null;
        $branding = is_array($item['brandingSettings'] ?? null) ? $item['brandingSettings'] : [];
        $brandingImage = is_array($branding['image'] ?? null) ? $branding['image'] : [];
        $banner = isset($brandingImage['bannerExternalUrl']) && is_string($brandingImage['bannerExternalUrl'])
            ? $brandingImage['bannerExternalUrl']
            : null;

        return new PlatformChannelDto(
            platform: PlatformKey::Youtube,
            externalId: $id,
            handle: $customUrl !== '' ? $customUrl : null,
            displayName: isset($snippet['title']) ? (string) $snippet['title'] : null,
            description: isset($snippet['description']) ? (string) $snippet['description'] : null,
            thumbnailUrl: $this->pickThumbnail($thumbnails),
            url: $id !== '' ? 'https://www.youtube.com/channel/'.$id : null,
            bannerUrl: $banner !== null && $banner !== '' ? $banner : null,
            platformCreatedAt: $this->parseTime($snippet['publishedAt'] ?? null),
            raw: null,
        );
    }

    /**
     * @param  array<string, mixed>  $thumbnails
     */
    private function pickThumbnail(array $thumbnails): ?string
    {
        foreach (['high', 'medium', 'default', 'standard', 'maxres'] as $key) {
            $entry = $thumbnails[$key] ?? null;
            if (is_array($entry) && isset($entry['url']) && is_string($entry['url']) && $entry['url'] !== '') {
                return $entry['url'];
            }
        }

        return null;
    }

    private function parseTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseIso8601Duration(?string $duration): ?int
    {
        if ($duration === null || $duration === '') {
            return null;
        }

        try {
            $interval = new \DateInterval($duration);
        } catch (\Throwable) {
            return null;
        }

        return ($interval->d * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    }
}
