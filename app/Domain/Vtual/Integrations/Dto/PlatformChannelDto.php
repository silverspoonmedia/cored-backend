<?php

namespace App\Domain\Vtual\Integrations\Dto;

use App\Domain\Vtual\Enums\PlatformKey;
use Carbon\CarbonInterface;

/**
 * Normalized channel/user resource for workers (maps toward vtual_channels).
 *
 * Intersection fields (YouTube + Twitch from existing channel/user endpoints):
 * externalId, handle, displayName, description, thumbnailUrl (avatar),
 * bannerUrl, url, platformCreatedAt.
 */
final class PlatformChannelDto
{
    /**
     * @param  array<string, mixed>|null  $raw
     */
    public function __construct(
        public readonly PlatformKey $platform,
        public readonly string $externalId,
        public readonly ?string $handle,
        public readonly ?string $displayName,
        public readonly ?string $description,
        public readonly ?string $thumbnailUrl,
        public readonly ?string $url,
        public readonly ?string $bannerUrl = null,
        public readonly ?CarbonInterface $platformCreatedAt = null,
        public readonly ?int $subscriberCount = null,
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
            'handle' => $this->handle,
            'display_name' => $this->displayName,
            'description' => $this->description,
            'thumbnail_url' => $this->thumbnailUrl,
            'banner_url' => $this->bannerUrl,
            'url' => $this->url,
            'platform_created_at' => $this->platformCreatedAt?->toIso8601String(),
            'subscriber_count' => $this->subscriberCount,
        ];
    }
}
