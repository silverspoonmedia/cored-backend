<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Integrations\Dto\PlatformStreamDto;
use App\Domain\Vtual\Integrations\Dto\PlatformVideoDto;

/**
 * Maps normalized platform video DTOs to ContentService upsert payloads.
 */
class PlatformVideoDtoMapper
{
    public function __construct(
        private readonly ContentCategoryClassifier $classifier,
        private readonly ContentKindClassifier $kinds,
    ) {}

    /**
     * @return array{
     *     external_id: string,
     *     type: string,
     *     status: string,
     *     title?: string|null,
     *     description?: string|null,
     *     thumbnail_url?: string|null,
     *     url?: string|null,
     *     concurrent_viewers?: int|null,
     *     scheduled_at?: mixed,
     *     started_at?: mixed,
     *     ended_at?: mixed,
     *     published_at?: mixed,
     *     content_type?: string|null,
     *     category_id?: string|null,
     *     duration_seconds?: int|null,
     *     topic_categories?: list<string>,
     *     tags?: list<string>,
     *     region_allowed?: list<string>,
     *     region_blocked?: list<string>,
     * }
     */
    public function toUpsertPayload(PlatformVideoDto $dto): array
    {
        [$type, $status] = $this->mapTypeAndStatus($dto->status);

        $payload = [
            'external_id' => $dto->externalId,
            'type' => $type->value,
            'status' => $status->value,
            'title' => $dto->title,
            'description' => $dto->description,
            'thumbnail_url' => $dto->thumbnailUrl,
            'url' => $dto->url,
            'concurrent_viewers' => $dto->concurrentViewers,
            'published_at' => $dto->publishedAt,
            'started_at' => $dto->startedAt,
            'ended_at' => $dto->endedAt,
            'category_id' => $dto->categoryId,
            'duration_seconds' => $dto->durationSeconds,
            'topic_categories' => $dto->topicCategories,
            'tags' => $dto->tags,
            'region_allowed' => $dto->regionAllowed,
            'region_blocked' => $dto->regionBlocked,
            ...$this->classificationPayload($dto, $type->value),
        ];

        if ($status === ContentStatus::Scheduled) {
            $payload['scheduled_at'] = $dto->startedAt ?? $dto->publishedAt;
        }

        return $payload;
    }

    /**
     * Map a live stream DTO (Twitch /streams) to a live content upsert payload.
     * The stream session id is the content external_id — each stream session
     * becomes its own content row, mirroring YouTube live rows.
     *
     * Twitch exposes neither `topicCategories` nor uploader tags, so the stream
     * category (`game_name`) is the only music signal a live row carries. It is
     * forwarded as `category_name` — matched against the configured music
     * categories in `ContentKindClassifier` — and, when it is one of them, also
     * as a derived tag so the sub-category layer (which reads tags) sees the
     * same evidence. A non-music category contributes no tag: a derived label
     * would otherwise be indistinguishable from an uploader-authored one.
     *
     * @return array{
     *     external_id: string,
     *     type: string,
     *     status: string,
     *     title?: string|null,
     *     description?: string|null,
     *     thumbnail_url?: string|null,
     *     url?: string|null,
     *     concurrent_viewers?: int|null,
     *     started_at?: mixed,
     *     published_at?: mixed,
     *     category_name?: string|null,
     *     tags?: list<string>,
     * }
     */
    public function toUpsertPayloadFromStream(PlatformStreamDto $dto): array
    {
        return [
            'external_id' => $dto->externalId,
            'type' => ContentType::Live->value,
            'status' => ContentStatus::Live->value,
            'title' => $dto->title,
            'description' => $dto->gameName,
            'thumbnail_url' => $dto->thumbnailUrl,
            'url' => $dto->url,
            'concurrent_viewers' => $dto->concurrentViewers,
            'started_at' => $dto->startedAt,
            'published_at' => $dto->startedAt,
            'category_name' => $dto->gameName,
            'tags' => $this->streamTags($dto),
        ];
    }

    /**
     * Tags a live payload can honestly claim. Only the platform-declared music
     * category qualifies today — the category name itself, so the tag vocabulary
     * (`tag_singing_stream` carries `karaoke stream`, `tag_cover` carries
     * `cover`) can match it without a second config list.
     *
     * @return list<string>
     */
    private function streamTags(PlatformStreamDto $dto): array
    {
        $category = trim((string) $dto->gameName);
        if ($category === '') {
            return [];
        }

        if (! $this->kinds->hasMusicCategory($category)) {
            return [];
        }

        return [$category];
    }

    /**
     * @return array{0: ContentType, 1: ContentStatus}
     */
    private function mapTypeAndStatus(string $dtoStatus): array
    {
        return match ($dtoStatus) {
            'live' => [ContentType::Live, ContentStatus::Live],
            'upcoming' => [ContentType::Live, ContentStatus::Scheduled],
            'ended' => [ContentType::Archive, ContentStatus::Ended],
            'uploaded' => [ContentType::Vod, ContentStatus::Uploaded],
            default => [ContentType::Vod, ContentStatus::Unknown],
        };
    }

    /**
     * Classification payload (never throws; unknown → both keys null).
     *
     * The music/non-music verdict comes from ContentKindClassifier, which reads
     * the full signal set (Music topic, categoryId, category name, title/tags
     * keywords, original credit, duration) — categoryId alone mislabels vTuber
     * covers filed under Gaming / People & Blogs.
     *
     * @return array{content_category: string|null, content_category_detail: string|null, content_type: string|null}
     */
    private function classificationPayload(PlatformVideoDto $dto, string $type): array
    {
        $video = [
            'title' => $dto->title,
            'description' => $dto->description,
            'category_id' => $dto->categoryId,
            'duration_seconds' => $dto->durationSeconds,
            'type' => $type,
            'topic_categories' => $dto->topicCategories,
            'tags' => $dto->tags,
            'channel_title' => $dto->channelTitle,
        ];

        $classified = $this->classifier->classify($video);

        return [
            'content_category' => $classified !== null ? $classified['category']->value : null,
            'content_category_detail' => $classified['detail'] ?? null,
            'content_type' => $this->kinds->kind($video)?->value,
        ];
    }
}
