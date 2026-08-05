<?php

namespace App\Domain\Vtual\Integrations\Support;

use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Dto\PlatformChannelDto;
use App\Domain\Vtual\Models\Platform;

/**
 * Shared bio-token check: re-resolve platform profile and look for token in description.
 */
class ChannelBioTokenVerifier
{
    public function __construct(
        private readonly ChannelProfileResolver $profiles,
    ) {}

    /**
     * @return array{dto: PlatformChannelDto, found: bool}
     */
    public function check(PlatformKey $platform, string $externalId, string $token): array
    {
        $dto = $this->profiles->resolveByExternalId($platform, $externalId);
        $description = $dto->description;
        $found = $token !== ''
            && $description !== null
            && str_contains($description, $token);

        return ['dto' => $dto, 'found' => $found];
    }

    public function platformKeyFromModel(Platform $platform): PlatformKey
    {
        $key = $platform->key;
        if ($key instanceof PlatformKey) {
            return $key;
        }

        return PlatformKey::from((string) $key);
    }

    public function mintToken(string $configKeyPrefix = 'vtual.channel_claim'): string
    {
        $prefix = (string) setting_config($configKeyPrefix.'.token_prefix', setting_config('vtual.claim.token_prefix', 'vtual-ch-'));
        if ($configKeyPrefix === 'vtual.channel_claim') {
            $prefix = (string) setting_config('vtual.channel_claim.token_prefix', 'vtual-ch-');
        }
        $bytes = max(3, (int) setting_config($configKeyPrefix.'.token_bytes', setting_config('vtual.claim.token_bytes', 6)));

        return $prefix.bin2hex(random_bytes($bytes));
    }

    public function assertPlatformEnabled(Platform $platform): void
    {
        if (! $platform->isEnabled()) {
            throw new VtualException(__('api/vtual.channel.platform_disabled'), 422);
        }
    }
}
