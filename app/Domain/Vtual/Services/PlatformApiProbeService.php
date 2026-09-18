<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ContentKind;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\PlatformApiGateway;
use App\Domain\Vtual\Integrations\Dto\PlatformVideoDto;
use App\Domain\Vtual\Integrations\Support\ChannelProfileInputKind;
use App\Domain\Vtual\Integrations\Support\ChannelProfileInputParser;
use App\Domain\Vtual\Integrations\Support\ChannelProfileResolver;
use App\Domain\Vtual\Integrations\Support\PlatformDtoSerializer;
use Illuminate\Support\Collection;

/**
 * Permanent Root/Admin probe of production PlatformApiGateway (live platform HTTP).
 *
 * Contract (agents — non-negotiable):
 * - Must only invoke PlatformApiGateway for platform reads (same path as workers).
 * - May use shared production helpers for input only (ChannelProfileInputParser /
 *   ChannelProfileResolver for channel/stream profiles). Never reimplement Helix/YouTube
 *   HTTP, alternate query builders, or probe-only DTO mapping.
 * - Output = serialize gateway DTOs (+ optional resolved map for debug transparency).
 * - Documented exception: video methods append a debug-only content_category preview
 *   from ContentCategoryClassifier (never persisted — persistence happens on hydrate).
 * - When gateway methods are added/removed/renamed for manual testing, update METHODS,
 *   run(), request validation, web i18n, tests, and docs/domains/vtual/README.md in the same PR.
 */
class PlatformApiProbeService
{
    /**
     * Probeable gateway operations (1:1 with run() match). Keep in sync with
     * PlatformApiGateway worker-facing surface when that surface changes.
     *
     * @var list<string>
     */
    public const METHODS = [
        'fetch_videos',
        'fetch_videos_chunked',
        'fetch_channels',
        'resolve_channel',
        'fetch_streams',
    ];

    public function __construct(
        private readonly PlatformApiGateway $gateway,
        private readonly ChannelProfileResolver $profiles,
        private readonly ContentCategoryClassifier $classifier,
    ) {}

    /**
     * @param  array{
     *     platform: string,
     *     method: string,
     *     ids?: list<string>|null,
     *     id?: string|null,
     *     handle?: string|null,
     *     parts?: list<string>|null,
     * }  $input
     * @return array{
     *     platform: string,
     *     method: string,
     *     count: int|null,
     *     resolved: list<array{input: string, external_id: string, handle: string|null}>,
     *     data: mixed
     * }
     */
    public function run(array $input): array
    {
        $platform = PlatformKey::from((string) $input['platform']);
        $method = (string) $input['method'];

        if (! in_array($method, self::METHODS, true)) {
            throw new VtualException(__('api/vtual.integrations.probe_unknown_method', [
                'method' => $method,
            ]), 422);
        }

        $parts = $input['parts'] ?? null;
        if (is_array($parts)) {
            $parts = array_values(array_filter($parts, fn (mixed $p): bool => is_string($p) && trim($p) !== ''));
            if ($parts === []) {
                $parts = null;
            }
        } else {
            $parts = null;
        }

        $resolved = [];

        $result = match ($method) {
            'fetch_videos' => $this->gateway->fetchVideos($platform, $this->requireIds($input), $parts),
            'fetch_videos_chunked' => $this->gateway->fetchVideosChunked($platform, $this->requireIds($input), $parts),
            'fetch_channels' => $this->runFetchChannels($platform, $input, $parts, $resolved),
            'resolve_channel' => $this->gateway->resolveChannel($platform, $this->requireHandle($input)),
            'fetch_streams' => $this->runFetchStreams($platform, $input, $resolved),
        };

        $data = PlatformDtoSerializer::serialize($result);
        $count = $result instanceof Collection ? $result->count() : ($result === null ? 0 : 1);

        if (in_array($method, ['fetch_videos', 'fetch_videos_chunked'], true) && is_array($data)) {
            $data = $this->appendClassificationPreview($result, $data);
        }

        return [
            'platform' => $platform->value,
            'method' => $method,
            'count' => $count,
            'resolved' => $resolved,
            'data' => $data,
        ];
    }

    /**
     * Debug-only classification preview appended to video items. Never persisted —
     * persistence happens on the hydrate path (PlatformVideoDtoMapper → ContentService).
     *
     * @param  Collection<int, mixed>  $result
     * @param  list<array<string, mixed>>  $serialized
     * @return list<array<string, mixed>>
     */
    private function appendClassificationPreview(Collection $result, array $serialized): array
    {
        $videos = $result->filter(fn (mixed $item): bool => $item instanceof PlatformVideoDto)->values();

        $out = [];
        $index = 0;
        foreach ($videos as $video) {
            /** @var PlatformVideoDto $video */
            if (! isset($serialized[$index])) {
                break;
            }

            $classified = $this->classifier->classify([
                'title' => $video->title,
                'description' => $video->description,
                'category_id' => $video->categoryId,
                'duration_seconds' => $video->durationSeconds,
                'type' => $this->contentCategoryTypeFor($video->status),
            ]);

            $category = $classified !== null ? $classified['category'] : null;

            $out[] = $serialized[$index] + [
                'content_type' => ContentKind::fromCategoryId($video->categoryId)?->value,
                'content_category' => $category !== null ? $category->value : null,
                'content_category_detail' => $classified['detail'] ?? null,
            ];
            $index++;
        }

        // Preserve any trailing items the serializer produced beyond video DTOs.
        for (; $index < count($serialized); $index++) {
            $out[] = $serialized[$index];
        }

        return $out;
    }

    /**
     * Map coarse DTO status to the content type string the classifier expects
     * (live|archive feed the singing-stream branch; vod otherwise).
     */
    private function contentCategoryTypeFor(string $dtoStatus): string
    {
        return match ($dtoStatus) {
            'live', 'upcoming' => 'live',
            'ended' => 'archive',
            default => 'vod',
        };
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>|null  $parts
     * @param  list<array{input: string, external_id: string, handle: string|null}>  $resolved
     * @return Collection<int, mixed>
     */
    private function runFetchChannels(PlatformKey $platform, array $input, ?array $parts, array &$resolved): Collection
    {
        $normalized = $this->normalizeChannelProfiles($platform, $this->requireIds($input));
        $resolved = $normalized['resolved'];

        return $this->gateway->fetchChannels($platform, $normalized['ids'], $parts);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<array{input: string, external_id: string, handle: string|null}>  $resolved
     * @return Collection<int, mixed>
     */
    private function runFetchStreams(PlatformKey $platform, array $input, array &$resolved): Collection
    {
        $normalized = $this->normalizeChannelProfiles($platform, $this->requireIds($input));
        $resolved = $normalized['resolved'];

        return $this->gateway->fetchStreamsByUserIds($platform, $normalized['ids']);
    }

    /**
     * Parse free-form profile entries → canonical external ids (deduped, order preserved).
     * channel_id: passthrough (no HTTP). handle/login/URL: ChannelProfileResolver.
     *
     * @param  list<string>  $entries
     * @return array{
     *     ids: list<string>,
     *     resolved: list<array{input: string, external_id: string, handle: string|null}>
     * }
     */
    private function normalizeChannelProfiles(PlatformKey $platform, array $entries): array
    {
        $resolved = [];
        $ids = [];
        $seen = [];

        foreach ($entries as $entry) {
            $parsed = ChannelProfileInputParser::parse($platform, $entry);

            if ($parsed->kind === ChannelProfileInputKind::ChannelId) {
                $externalId = $parsed->value;
                $handle = null;
            } else {
                $dto = $this->profiles->resolve($platform, $entry);
                $externalId = $dto->externalId;
                $handle = $dto->handle;
            }

            $resolved[] = [
                'input' => $entry,
                'external_id' => $externalId,
                'handle' => $handle,
            ];

            if (isset($seen[$externalId])) {
                continue;
            }
            $seen[$externalId] = true;
            $ids[] = $externalId;
        }

        return [
            'ids' => $ids,
            'resolved' => $resolved,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    private function requireIds(array $input): array
    {
        $ids = $input['ids'] ?? null;
        if (is_string($ids)) {
            $ids = preg_split('/[\s,]+/', $ids) ?: [];
        }
        if (! is_array($ids) || $ids === []) {
            $single = $input['id'] ?? null;
            if (is_string($single) && trim($single) !== '') {
                $ids = [trim($single)];
            }
        }
        if (! is_array($ids) || $ids === []) {
            throw new VtualException(__('api/vtual.integrations.probe_ids_required'), 422);
        }

        $clean = [];
        foreach ($ids as $id) {
            if (! is_string($id) && ! is_int($id)) {
                continue;
            }
            $value = trim((string) $id);
            if ($value !== '') {
                $clean[] = $value;
            }
        }

        if ($clean === []) {
            throw new VtualException(__('api/vtual.integrations.probe_ids_required'), 422);
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function requireHandle(array $input): string
    {
        $handle = $input['handle'] ?? $input['id'] ?? null;
        if (is_array($input['ids'] ?? null) && count($input['ids']) === 1) {
            $handle = $handle ?? $input['ids'][0];
        }
        if (! is_string($handle) || trim($handle) === '') {
            throw new VtualException(__('api/vtual.integrations.handle_required'), 422);
        }

        return trim($handle);
    }
}
