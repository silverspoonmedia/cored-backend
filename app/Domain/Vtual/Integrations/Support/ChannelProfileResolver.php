<?php

namespace App\Domain\Vtual\Integrations\Support;

use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Dto\PlatformChannelDto;
use App\Domain\Vtual\Integrations\PlatformApiGateway;

/**
 * Resolve free-form profile input to a canonical PlatformChannelDto via gateway.
 */
class ChannelProfileResolver
{
    public function __construct(
        private readonly PlatformApiGateway $gateway,
    ) {}

    public function resolve(PlatformKey $platform, string $profile): PlatformChannelDto
    {
        if ($platform === PlatformKey::Kick) {
            throw new VtualException(__('api/vtual.channel.platform_disabled'), 422);
        }

        $parsed = ChannelProfileInputParser::parse($platform, $profile);

        $dto = match ($parsed->kind) {
            ChannelProfileInputKind::ChannelId => $this->byChannelId($platform, $parsed->value),
            ChannelProfileInputKind::Handle => $this->byHandle($platform, $parsed->value),
        };

        if ($dto === null || $dto->externalId === '') {
            throw new VtualException(__('api/vtual.channel.profile_not_found'), 422);
        }

        return $this->normalizeHandle($dto);
    }

    /**
     * Re-fetch by stored canonical external_id (refresh path).
     */
    public function resolveByExternalId(PlatformKey $platform, string $externalId): PlatformChannelDto
    {
        $externalId = trim($externalId);
        if ($externalId === '') {
            throw new VtualException(__('api/vtual.channel.external_id_required'), 422);
        }

        $dto = $this->byChannelId($platform, $externalId);
        if ($dto === null || $dto->externalId === '') {
            throw new VtualException(__('api/vtual.channel.profile_not_found'), 422);
        }

        return $this->normalizeHandle($dto);
    }

    private function byChannelId(PlatformKey $platform, string $id): ?PlatformChannelDto
    {
        $collection = $this->gateway->fetchChannels($platform, [$id]);
        $first = $collection->first();

        return $first instanceof PlatformChannelDto ? $first : null;
    }

    private function byHandle(PlatformKey $platform, string $handle): ?PlatformChannelDto
    {
        return $this->gateway->resolveChannel($platform, $handle);
    }

    /**
     * Store bare handle (no leading @); Twitch login already lowercased by client/parser.
     */
    private function normalizeHandle(PlatformChannelDto $dto): PlatformChannelDto
    {
        $handle = $dto->handle;
        if (is_string($handle)) {
            $handle = ltrim(trim($handle), '@');
            if ($dto->platform === PlatformKey::Twitch) {
                $handle = strtolower($handle);
            }
            if ($handle === '') {
                $handle = null;
            }
        }

        if ($handle === $dto->handle) {
            return $dto;
        }

        return new PlatformChannelDto(
            platform: $dto->platform,
            externalId: $dto->externalId,
            handle: $handle,
            displayName: $dto->displayName,
            description: $dto->description,
            thumbnailUrl: $dto->thumbnailUrl,
            url: $dto->url,
            bannerUrl: $dto->bannerUrl,
            platformCreatedAt: $dto->platformCreatedAt,
            raw: $dto->raw,
        );
    }
}
