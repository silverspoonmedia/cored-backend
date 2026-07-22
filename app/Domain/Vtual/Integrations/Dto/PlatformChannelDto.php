<?php

namespace App\Domain\Vtual\Integrations\Dto;

use App\Domain\Vtual\Enums\PlatformKey;

/**
 * Normalized channel/user resource for workers (maps toward vtual_channels).
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
            'url' => $this->url,
        ];
    }
}
