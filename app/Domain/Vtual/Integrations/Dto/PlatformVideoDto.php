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
}
