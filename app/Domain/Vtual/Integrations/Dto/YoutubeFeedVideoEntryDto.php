<?php

namespace App\Domain\Vtual\Integrations\Dto;

use Illuminate\Support\Carbon;

/**
 * Lightweight entry from YouTube public Atom feed (zero API quota).
 */
final class YoutubeFeedVideoEntryDto
{
    public function __construct(
        public readonly string $videoId,
        public readonly ?Carbon $publishedAt,
        public readonly ?Carbon $updatedAt,
        public readonly ?string $title,
        public readonly ?string $url,
    ) {}
}
