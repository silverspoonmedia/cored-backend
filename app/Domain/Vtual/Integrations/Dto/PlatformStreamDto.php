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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'platform' => $this->platform->value,
            'external_id' => $this->externalId,
            'user_external_id' => $this->userExternalId,
            'user_login' => $this->userLogin,
            'user_name' => $this->userName,
            'title' => $this->title,
            'game_name' => $this->gameName,
            'thumbnail_url' => $this->thumbnailUrl,
            'url' => $this->url,
            'concurrent_viewers' => $this->concurrentViewers,
            'started_at' => $this->startedAt?->toIso8601String(),
            'language' => $this->language,
            'is_live' => $this->isLive,
        ];
    }
}
