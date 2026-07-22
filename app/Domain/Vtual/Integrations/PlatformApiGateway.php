<?php

namespace App\Domain\Vtual\Integrations;

use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\UnsupportedPlatformOperationException;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Contracts\FetchesChannels;
use App\Domain\Vtual\Integrations\Contracts\FetchesStreams;
use App\Domain\Vtual\Integrations\Contracts\FetchesVideos;
use App\Domain\Vtual\Integrations\Contracts\PlatformClient;
use App\Domain\Vtual\Integrations\Contracts\ResolvesChannel;
use App\Domain\Vtual\Integrations\Dto\PlatformChannelDto;
use App\Domain\Vtual\Integrations\Enums\PlatformApiOperation;
use App\Domain\Vtual\Integrations\Support\BatchIdChunker;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Models\PlatformCredential;
use App\Domain\Vtual\Services\PlatformCredentialService;
use Illuminate\Support\Collection;

/**
 * Worker-facing facade for manual platform HTTP reads.
 * Picks active credential, binds client, dispatches by capability.
 */
class PlatformApiGateway
{
    public function __construct(
        private readonly PlatformClientRegistry $registry,
        private readonly PlatformCredentialService $credentials,
    ) {}

    public function client(PlatformKey|Platform|string $platform): PlatformClient
    {
        $key = $this->resolveKey($platform);
        $model = $this->platformModel($key);
        if (! $model->isEnabled()) {
            throw new VtualException(__('api/vtual.integrations.platform_disabled', [
                'platform' => $key->value,
            ]), 422);
        }

        $credential = $this->requireActiveCredential($model);

        return $this->registry->for($key)->withCredential($credential);
    }

    /**
     * @param  list<string|int>  $externalIds
     * @param  list<string>|null  $parts
     * @return Collection<int, \App\Domain\Vtual\Integrations\Dto\PlatformVideoDto>
     */
    public function fetchVideos(PlatformKey|Platform|string $platform, array $externalIds, ?array $parts = null): Collection
    {
        $client = $this->client($platform);
        $this->assertSupports($client, PlatformApiOperation::FetchVideos);
        /** @var PlatformClient&FetchesVideos $client */
        if (! $client instanceof FetchesVideos) {
            throw new UnsupportedPlatformOperationException($client->key(), PlatformApiOperation::FetchVideos);
        }

        return $client->fetchVideos($externalIds, $parts);
    }

    /**
     * Auto-split ids into batches of config max (multiple HTTP calls).
     *
     * @param  list<string|int>  $externalIds
     * @param  list<string>|null  $parts
     * @return Collection<int, \App\Domain\Vtual\Integrations\Dto\PlatformVideoDto>
     */
    public function fetchVideosChunked(PlatformKey|Platform|string $platform, array $externalIds, ?array $parts = null): Collection
    {
        $max = (int) config('vtual.http.batch.videos', 50);
        $chunks = BatchIdChunker::chunk($externalIds, $max, 'video ids');
        $all = collect();
        foreach ($chunks as $chunk) {
            $all = $all->merge($this->fetchVideos($platform, $chunk, $parts));
        }

        return $all->values();
    }

    /**
     * @param  list<string|int>  $externalIds
     * @param  list<string>|null  $parts
     * @return Collection<int, PlatformChannelDto>
     */
    public function fetchChannels(PlatformKey|Platform|string $platform, array $externalIds, ?array $parts = null): Collection
    {
        $client = $this->client($platform);
        $this->assertSupports($client, PlatformApiOperation::FetchChannels);
        if (! $client instanceof FetchesChannels) {
            throw new UnsupportedPlatformOperationException($client->key(), PlatformApiOperation::FetchChannels);
        }

        return $client->fetchChannels($externalIds, $parts);
    }

    public function resolveChannel(PlatformKey|Platform|string $platform, string $handleOrLogin): ?PlatformChannelDto
    {
        $client = $this->client($platform);
        $this->assertSupports($client, PlatformApiOperation::ResolveChannel);
        if (! $client instanceof ResolvesChannel) {
            throw new UnsupportedPlatformOperationException($client->key(), PlatformApiOperation::ResolveChannel);
        }

        return $client->resolveChannel($handleOrLogin);
    }

    /**
     * Twitch-only (and future stream platforms). Throws on YouTube.
     *
     * @param  list<string|int>  $userExternalIds
     * @return Collection<int, \App\Domain\Vtual\Integrations\Dto\PlatformStreamDto>
     */
    public function fetchStreamsByUserIds(PlatformKey|Platform|string $platform, array $userExternalIds): Collection
    {
        $client = $this->client($platform);
        $this->assertSupports($client, PlatformApiOperation::FetchStreams);
        if (! $client instanceof FetchesStreams) {
            throw new UnsupportedPlatformOperationException($client->key(), PlatformApiOperation::FetchStreams);
        }

        return $client->fetchStreamsByUserIds($userExternalIds);
    }

    private function assertSupports(PlatformClient $client, PlatformApiOperation $operation): void
    {
        if (! $client->supports($operation)) {
            throw new UnsupportedPlatformOperationException($client->key(), $operation);
        }
    }

    private function requireActiveCredential(Platform $platform): PlatformCredential
    {
        $credential = $this->credentials->pickActive($platform);
        if ($credential === null) {
            throw new VtualException(__('api/vtual.integrations.no_active_credential', [
                'platform' => $platform->key instanceof PlatformKey
                    ? $platform->key->value
                    : (string) $platform->key,
            ]), 422);
        }

        return $credential;
    }

    private function resolveKey(PlatformKey|Platform|string $platform): PlatformKey
    {
        if ($platform instanceof PlatformKey) {
            return $platform;
        }
        if ($platform instanceof Platform) {
            $key = $platform->key;
            if ($key instanceof PlatformKey) {
                return $key;
            }

            return PlatformKey::from((string) $key);
        }

        return PlatformKey::from($platform);
    }

    private function platformModel(PlatformKey $key): Platform
    {
        $model = Platform::query()->where('key', $key->value)->first();
        if ($model === null) {
            throw new VtualException(__('api/vtual.integrations.platform_unknown', [
                'platform' => $key->value,
            ]), 422);
        }

        return $model;
    }
}
