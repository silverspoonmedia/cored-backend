<?php

namespace App\Domain\Vtual\Integrations\Dto;

use App\Domain\Vtual\Enums\PlatformKey;
use Illuminate\Support\Carbon;

/**
 * Normalized video/archive resource for workers (maps toward vtual_contents).
 */
final class PlatformVideoDto
{
    /**
     * @param  array<string, mixed>|null  $raw  Optional debug payload; null in production paths
     */
    public function __construct(
        public readonly PlatformKey $platform,
        public readonly string $externalId,
        public readonly ?string $channelExternalId,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly ?string $thumbnailUrl,
        public readonly ?string $url,
        /** YouTube snippet.categoryId (e.g. "10" = Music); null for other platforms. */
        public readonly ?string $categoryId,
        /** Coarse: live|upcoming|ended|uploaded|unknown */
        public readonly string $status,
        public readonly ?Carbon $publishedAt,
        public readonly ?Carbon $startedAt,
        public readonly ?Carbon $endedAt,
        public readonly ?int $durationSeconds,
        public readonly ?int $viewCount,
        public readonly ?int $concurrentViewers,
        public readonly ?array $raw = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'platform' => $this->platform->value,
            'external_id' => $this->externalId,
            'channel_external_id' => $this->channelExternalId,
            'title' => $this->title,
            'description' => $this->description,
            'thumbnail_url' => $this->thumbnailUrl,
            'url' => $this->url,
            'status' => $this->status,
            'published_at' => $this->publishedAt?->toIso8601String(),
            'started_at' => $this->startedAt?->toIso8601String(),
            'ended_at' => $this->endedAt?->toIso8601String(),
            'duration_seconds' => $this->durationSeconds,
            'view_count' => $this->viewCount,
            'concurrent_viewers' => $this->concurrentViewers,
        ];
    }
}
