<?php

namespace App\Domain\Vtual\Integrations\Twitch;

use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\PlatformApiException;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Concerns\UsesPlatformCredential;
use App\Domain\Vtual\Integrations\Contracts\FetchesChannels;
use App\Domain\Vtual\Integrations\Contracts\FetchesStreams;
use App\Domain\Vtual\Integrations\Contracts\FetchesVideos;
use App\Domain\Vtual\Integrations\Contracts\PlatformClient;
use App\Domain\Vtual\Integrations\Contracts\ResolvesChannel;
use App\Domain\Vtual\Integrations\Dto\PlatformChannelDto;
use App\Domain\Vtual\Integrations\Dto\PlatformStreamDto;
use App\Domain\Vtual\Integrations\Dto\PlatformVideoDto;
use App\Domain\Vtual\Integrations\Enums\PlatformApiOperation;
use App\Domain\Vtual\Integrations\Support\BatchIdChunker;
use App\Domain\Vtual\Integrations\Support\PlatformHttp;
use App\Domain\Vtual\Services\PlatformCredentialService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TwitchHelixClient implements PlatformClient, FetchesVideos, FetchesChannels, ResolvesChannel, FetchesStreams
{
    use UsesPlatformCredential;

    public function __construct(
        private readonly PlatformCredentialService $credentials,
    ) {}

    public function key(): PlatformKey
    {
        return PlatformKey::Twitch;
    }

    public function supports(PlatformApiOperation $operation): bool
    {
        return match ($operation) {
            PlatformApiOperation::FetchVideos,
            PlatformApiOperation::FetchChannels,
            PlatformApiOperation::ResolveChannel,
            PlatformApiOperation::FetchStreams => true,
            PlatformApiOperation::ListChannelVideos,
            PlatformApiOperation::SearchChannelVideos => false,
        };
    }

    public function fetchVideos(array $externalIds, ?array $parts = null): Collection
    {
        unset($parts);
        $max = (int) config('vtual.http.batch.videos', 50);
        $ids = BatchIdChunker::normalize($externalIds, $max, 'video ids');

        try {
            $response = $this->helixGet('/videos', $this->repeatedParam('id', $ids), 'fetch_videos');
        } catch (PlatformApiException $e) {
            $this->handleAuthFailure($e, $this->credentials);
        }

        $this->credentials->markUsed($this->requireCredential());

        return $this->mapDataCollection(
            $response->json('data'),
            fn (array $item): PlatformVideoDto => $this->mapVideo($item),
        );
    }

    public function fetchChannels(array $externalIds, ?array $parts = null): Collection
    {
        unset($parts);
        $max = (int) config('vtual.http.batch.channels', 50);
        $ids = BatchIdChunker::normalize($externalIds, $max, 'channel ids');

        try {
            $response = $this->helixGet('/users', $this->repeatedParam('id', $ids), 'fetch_channels');
        } catch (PlatformApiException $e) {
            $this->handleAuthFailure($e, $this->credentials);
        }

        $this->credentials->markUsed($this->requireCredential());

        return $this->mapDataCollection(
            $response->json('data'),
            fn (array $item): PlatformChannelDto => $this->mapUser($item),
        );
    }

    public function resolveChannel(string $handleOrLogin): ?PlatformChannelDto
    {
        $login = ltrim(trim($handleOrLogin), '@');
        if ($login === '') {
            throw new VtualException(__('api/vtual.integrations.handle_required'), 422);
        }

        try {
            $response = $this->helixGet('/users', ['login' => strtolower($login)], 'resolve_channel');
        } catch (PlatformApiException $e) {
            $this->handleAuthFailure($e, $this->credentials);
        }

        $this->credentials->markUsed($this->requireCredential());

        $data = $response->json('data') ?? [];
        if (! is_array($data) || $data === [] || ! is_array($data[0] ?? null)) {
            return null;
        }

        return $this->mapUser($data[0]);
    }

    public function fetchStreamsByUserIds(array $userExternalIds): Collection
    {
        $max = (int) config('vtual.http.batch.streams_user_ids', 100);
        $ids = BatchIdChunker::normalize($userExternalIds, $max, 'user ids');

        try {
            $response = $this->helixGet('/streams', $this->repeatedParam('user_id', $ids), 'fetch_streams');
        } catch (PlatformApiException $e) {
            $this->handleAuthFailure($e, $this->credentials);
        }

        $this->credentials->markUsed($this->requireCredential());

        return $this->mapDataCollection(
            $response->json('data'),
            fn (array $item): PlatformStreamDto => $this->mapStream($item),
        );
    }

    /**
     * @param  array<string, string|list<string>>  $query
     */
    private function helixGet(string $path, array $query, string $operation): Response
    {
        $url = $this->baseUrl().$path;
        $pairs = [];
        foreach ($query as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $pairs[] = rawurlencode((string) $key).'='.rawurlencode((string) $item);
                }
            } else {
                $pairs[] = rawurlencode((string) $key).'='.rawurlencode((string) $value);
            }
        }
        if ($pairs !== []) {
            $url .= '?'.implode('&', $pairs);
        }

        return PlatformHttp::get(
            $url,
            [],
            $this->authedRequest(),
            PlatformKey::Twitch->value,
            $this->httpLogContext($operation),
        );
    }

    /**
     * @param  list<string>  $values
     * @return array<string, list<string>>
     */
    private function repeatedParam(string $key, array $values): array
    {
        return [$key => $values];
    }

    private function authedRequest(): PendingRequest
    {
        $credential = $this->requireCredential();
        $secret = $this->credentials->resolvePayload($credential);
        $clientId = $secret['client_id'] ?? null;
        if (! is_string($clientId) || $clientId === '') {
            throw new VtualException(__('api/vtual.integrations.missing_client_id'), 422);
        }

        $token = $this->credentials->getAppAccessToken($credential);

        return PlatformHttp::client()->withHeaders([
            'Client-Id' => $clientId,
            'Authorization' => 'Bearer '.$token,
        ]);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('vtual.twitch.helix_base_url', 'https://api.twitch.tv/helix'), '/');
    }

    /**
     * @param  mixed  $data
     * @param  callable(array<string, mixed>): mixed  $mapper
     * @return Collection<int, mixed>
     */
    private function mapDataCollection(mixed $data, callable $mapper): Collection
    {
        if (! is_array($data)) {
            return collect();
        }

        return collect($data)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item) => $mapper($item))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function mapVideo(array $item): PlatformVideoDto
    {
        $id = (string) ($item['id'] ?? '');
        $type = is_string($item['type'] ?? null) ? $item['type'] : null;
        $status = match ($type) {
            'archive' => 'ended',
            'upload' => 'uploaded',
            'highlight' => 'uploaded',
            default => 'unknown',
        };

        $duration = is_string($item['duration'] ?? null) ? $item['duration'] : null;
        $thumbnail = is_string($item['thumbnail_url'] ?? null)
            ? str_replace(['%{width}', '%{height}'], ['640', '360'], $item['thumbnail_url'])
            : null;

        return new PlatformVideoDto(
            platform: PlatformKey::Twitch,
            externalId: $id,
            channelExternalId: isset($item['user_id']) ? (string) $item['user_id'] : null,
            title: isset($item['title']) ? (string) $item['title'] : null,
            description: isset($item['description']) ? (string) $item['description'] : null,
            thumbnailUrl: $thumbnail,
            url: isset($item['url']) ? (string) $item['url'] : ($id !== '' ? 'https://www.twitch.tv/videos/'.$id : null),
            categoryId: null,
            status: $status,
            publishedAt: $this->parseTime($item['published_at'] ?? $item['created_at'] ?? null),
            startedAt: $this->parseTime($item['created_at'] ?? null),
            endedAt: null,
            durationSeconds: $this->parseTwitchDuration($duration),
            viewCount: isset($item['view_count']) && is_numeric($item['view_count']) ? (int) $item['view_count'] : null,
            concurrentViewers: null,
            raw: null,
            // Helix exposes no topic/tag metadata; the music layer resolves to
            // null (unknown) for Twitch and is left to the AI audit.
            topicCategories: [],
            tags: [],
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function mapUser(array $item): PlatformChannelDto
    {
        $id = (string) ($item['id'] ?? '');
        $login = isset($item['login']) ? (string) $item['login'] : null;
        $offline = isset($item['offline_image_url']) ? (string) $item['offline_image_url'] : null;

        return new PlatformChannelDto(
            platform: PlatformKey::Twitch,
            externalId: $id,
            handle: $login,
            displayName: isset($item['display_name']) ? (string) $item['display_name'] : null,
            description: isset($item['description']) ? (string) $item['description'] : null,
            thumbnailUrl: isset($item['profile_image_url']) ? (string) $item['profile_image_url'] : null,
            url: $login !== null && $login !== '' ? 'https://www.twitch.tv/'.$login : null,
            bannerUrl: $offline !== null && $offline !== '' ? $offline : null,
            platformCreatedAt: $this->parseTime($item['created_at'] ?? null),
            raw: null,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function mapStream(array $item): PlatformStreamDto
    {
        $id = (string) ($item['id'] ?? '');
        $userId = (string) ($item['user_id'] ?? '');
        $login = isset($item['user_login']) ? (string) $item['user_login'] : null;
        $thumbnail = is_string($item['thumbnail_url'] ?? null)
            ? str_replace(['{width}', '{height}'], ['640', '360'], $item['thumbnail_url'])
            : null;

        return new PlatformStreamDto(
            platform: PlatformKey::Twitch,
            externalId: $id,
            userExternalId: $userId,
            userLogin: $login,
            userName: isset($item['user_name']) ? (string) $item['user_name'] : null,
            title: isset($item['title']) ? (string) $item['title'] : null,
            gameName: isset($item['game_name']) ? (string) $item['game_name'] : null,
            thumbnailUrl: $thumbnail,
            url: $login !== null && $login !== '' ? 'https://www.twitch.tv/'.$login : null,
            concurrentViewers: isset($item['viewer_count']) && is_numeric($item['viewer_count'])
                ? (int) $item['viewer_count']
                : null,
            startedAt: $this->parseTime($item['started_at'] ?? null),
            language: isset($item['language']) ? (string) $item['language'] : null,
            isLive: true,
            raw: null,
        );
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

    /**
     * Twitch duration strings like "1h2m3s" or "3m21s".
     */
    private function parseTwitchDuration(?string $duration): ?int
    {
        if ($duration === null || $duration === '') {
            return null;
        }

        $seconds = 0;
        if (preg_match('/(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?/', $duration, $m)) {
            $seconds += isset($m[1]) && $m[1] !== '' ? ((int) $m[1] * 3600) : 0;
            $seconds += isset($m[2]) && $m[2] !== '' ? ((int) $m[2] * 60) : 0;
            $seconds += isset($m[3]) && $m[3] !== '' ? (int) $m[3] : 0;
        }

        return $seconds > 0 ? $seconds : null;
    }
}
