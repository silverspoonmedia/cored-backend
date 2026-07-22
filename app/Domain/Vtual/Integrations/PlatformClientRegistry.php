<?php

namespace App\Domain\Vtual\Integrations;

use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Contracts\PlatformClient;
use App\Domain\Vtual\Integrations\Twitch\TwitchHelixClient;
use App\Domain\Vtual\Integrations\Youtube\YoutubeDataApiClient;

class PlatformClientRegistry
{
    public function __construct(
        private readonly YoutubeDataApiClient $youtube,
        private readonly TwitchHelixClient $twitch,
    ) {}

    public function for(PlatformKey $key): PlatformClient
    {
        return match ($key) {
            PlatformKey::Youtube => $this->youtube,
            PlatformKey::Twitch => $this->twitch,
            PlatformKey::Kick => throw new VtualException(
                __('api/vtual.integrations.platform_disabled', ['platform' => $key->value]),
                422,
            ),
        };
    }
}
