<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ContentKind;
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
        ];
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
     * @return array{content_category: string|null, content_category_detail: string|null}
     */
    private function classificationPayload(PlatformVideoDto $dto, string $type): array
    {
        $classified = $this->classifier->classify([
            'title' => $dto->title,
            'description' => $dto->description,
            'category_id' => $dto->categoryId,
            'duration_seconds' => $dto->durationSeconds,
            'type' => $type,
        ]);

        return [
            'content_category' => $classified !== null ? $classified['category']->value : null,
            'content_category_detail' => $classified['detail'] ?? null,
            'content_type' => ContentKind::fromCategoryId($dto->categoryId)?->value,
        ];
    }
}
