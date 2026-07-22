<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Contracts\PlatformCredentialProbe;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\PlatformCredential;
use App\Domain\Vtual\Services\Probes\BearerTokenProbe;
use App\Domain\Vtual\Services\Probes\TwitchOauthAppProbe;
use App\Domain\Vtual\Services\Probes\YoutubeApiKeyProbe;

class PlatformCredentialProbeRegistry
{
    /**
     * @var list<PlatformCredentialProbe>
     */
    private array $probes;

    public function __construct(
        YoutubeApiKeyProbe $youtube,
        TwitchOauthAppProbe $twitch,
        BearerTokenProbe $bearer,
    ) {
        $this->probes = [$youtube, $twitch, $bearer];
    }

    public function for(PlatformCredential $credential): PlatformCredentialProbe
    {
        $credential->loadMissing('platform');

        foreach ($this->probes as $probe) {
            if ($probe->supports($credential)) {
                return $probe;
            }
        }

        throw new VtualException(__('api/vtual.credential.test_unsupported'), 422);
    }
}
