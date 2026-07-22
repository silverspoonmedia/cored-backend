<?php

namespace App\Domain\Vtual\Integrations\Dto;

use App\Domain\Vtual\Enums\PlatformKey;
use Illuminate\Support\Carbon;

/**
 * Normalized live stream snapshot (Twitch streams; future platforms).
 */
final class PlatformStreamDto
{
    /**
     * @param  array<string, mixed>|null  $raw
     */
    public function __construct(
        public readonly PlatformKey $platform,
        public readonly string $externalId,
        public readonly string $userExternalId,
        public readonly ?string $userLogin,
        public readonly ?string $userName,
        public readonly ?string $title,
        public readonly ?string $gameName,
        public readonly ?string $thumbnailUrl,
        public readonly ?string $url,
        public readonly ?int $concurrentViewers,
        public readonly ?Carbon $startedAt,
        public readonly ?string $language,
        public readonly bool $isLive = true,
        public readonly ?array $raw = null,
    ) {}
}
