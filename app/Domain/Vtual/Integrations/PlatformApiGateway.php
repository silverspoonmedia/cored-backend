<?php

namespace App\Domain\Vtual\Integrations;

use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\PlatformApiException;
use App\Domain\Vtual\Exceptions\UnsupportedPlatformOperationException;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Contracts\FetchesChannels;
use App\Domain\Vtual\Integrations\Contracts\FetchesStreams;
use App\Domain\Vtual\Integrations\Contracts\FetchesVideos;
use App\Domain\Vtual\Integrations\Contracts\ListsChannelVideos;
use App\Domain\Vtual\Integrations\Contracts\PlatformClient;
use App\Domain\Vtual\Integrations\Contracts\ResolvesChannel;
use App\Domain\Vtual\Integrations\Dto\PlatformChannelDto;
use App\Domain\Vtual\Integrations\Enums\PlatformApiOperation;
use App\Domain\Vtual\Integrations\Support\BatchIdChunker;
use App\Domain\Vtual\Integrations\Support\PlatformErrorLogger;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Models\PlatformCredential;
use App\Domain\Vtual\Services\PlatformCredentialService;
use Illuminate\Support\Collection;

/**
 * Worker-facing facade for manual platform HTTP reads.
 * Picks active credential, binds client, dispatches by capability.
 * Soft failover (when enabled): re-pick after 401/403 within max_retries.
 */
class PlatformApiGateway
{
    public function __construct(
        private readonly PlatformClientRegistry $registry,
        private readonly PlatformCredentialService $credentials,
    ) {}

    /**
     * Single-pick client (no in-call failover). Prefer fetch* methods for workers.
     */
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
        return $this->runWithCredentialFailover($platform, function (PlatformClient $client) use ($externalIds, $parts): Collection {
            $this->assertSupports($client, PlatformApiOperation::FetchVideos);
            if (! $client instanceof FetchesVideos) {
                throw new UnsupportedPlatformOperationException($client->key(), PlatformApiOperation::FetchVideos);
            }

            return $client->fetchVideos($externalIds, $parts);
        });
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
     * List recent uploads for a channel (platform-specific catalog read).
     *
     * @return Collection<int, string> Platform video external ids (newest first)
     */
    public function listChannelVideos(
        PlatformKey|Platform|string $platform,
        string $channelExternalId,
        int $limit = 50,
    ): Collection {
        return $this->runWithCredentialFailover($platform, function (PlatformClient $client) use ($channelExternalId, $limit): Collection {
            $this->assertSupports($client, PlatformApiOperation::ListChannelVideos);
            if (! $client instanceof ListsChannelVideos) {
                throw new UnsupportedPlatformOperationException($client->key(), PlatformApiOperation::ListChannelVideos);
            }

            return $client->listChannelVideos($channelExternalId, $limit);
        });
    }

    /**
     * @param  list<string|int>  $externalIds
     * @param  list<string>|null  $parts
     * @return Collection<int, PlatformChannelDto>
     */
    public function fetchChannels(PlatformKey|Platform|string $platform, array $externalIds, ?array $parts = null): Collection
    {
        return $this->runWithCredentialFailover($platform, function (PlatformClient $client) use ($externalIds, $parts): Collection {
            $this->assertSupports($client, PlatformApiOperation::FetchChannels);
            if (! $client instanceof FetchesChannels) {
                throw new UnsupportedPlatformOperationException($client->key(), PlatformApiOperation::FetchChannels);
            }

            return $client->fetchChannels($externalIds, $parts);
        });
    }

    public function resolveChannel(PlatformKey|Platform|string $platform, string $handleOrLogin): ?PlatformChannelDto
    {
        return $this->runWithCredentialFailover($platform, function (PlatformClient $client) use ($handleOrLogin): ?PlatformChannelDto {
            $this->assertSupports($client, PlatformApiOperation::ResolveChannel);
            if (! $client instanceof ResolvesChannel) {
                throw new UnsupportedPlatformOperationException($client->key(), PlatformApiOperation::ResolveChannel);
            }

            return $client->resolveChannel($handleOrLogin);
        });
    }

    /**
     * Twitch-only (and future stream platforms). Throws on YouTube.
     *
     * @param  list<string|int>  $userExternalIds
     * @return Collection<int, \App\Domain\Vtual\Integrations\Dto\PlatformStreamDto>
     */
    public function fetchStreamsByUserIds(PlatformKey|Platform|string $platform, array $userExternalIds): Collection
    {
        return $this->runWithCredentialFailover($platform, function (PlatformClient $client) use ($userExternalIds): Collection {
            $this->assertSupports($client, PlatformApiOperation::FetchStreams);
            if (! $client instanceof FetchesStreams) {
                throw new UnsupportedPlatformOperationException($client->key(), PlatformApiOperation::FetchStreams);
            }

            return $client->fetchStreamsByUserIds($userExternalIds);
        });
    }

    /**
     * Bind eligible credential(s); on 401/403 optionally retry once with next pick.
     *
     * @template T
     *
     * @param  callable(PlatformClient): T  $fn
     * @return T
     */
    private function runWithCredentialFailover(PlatformKey|Platform|string $platform, callable $fn): mixed
    {
        $key = $this->resolveKey($platform);
        $model = $this->platformModel($key);
        if (! $model->isEnabled()) {
            throw new VtualException(__('api/vtual.integrations.platform_disabled', [
                'platform' => $key->value,
            ]), 422);
        }

        $enabled = (bool) config('vtual.credential.failover.enabled', false);
        $maxRetries = max(0, (int) config('vtual.credential.failover.max_retries_per_call', 1));
        $excludeIds = [];
        $attempt = 0;
        $lastAuthException = null;
        $previousCredentialId = null;

        while (true) {
            $credential = $this->credentials->pickActive($model, $excludeIds);
            if ($credential === null) {
                if ($lastAuthException instanceof PlatformApiException) {
                    throw $lastAuthException;
                }

                throw new VtualException(__('api/vtual.integrations.no_active_credential', [
                    'platform' => $key->value,
                ]), 422);
            }

            $excludeIds[] = (string) $credential->id;

            if ($previousCredentialId !== null && $enabled) {
                PlatformErrorLogger::log('Platform credential failover switch', [
                    'component' => 'platform_credential',
                    'event' => 'credential_failover_switch',
                    'platform' => $key->value,
                    'from_credential_id' => $previousCredentialId,
                    'to_credential_id' => (string) $credential->id,
                ]);
            }

            try {
                $client = $this->registry->for($key)->withCredential($credential);

                return $fn($client);
            } catch (PlatformApiException $e) {
                if (! in_array($e->upstreamStatus, [401, 403], true)) {
                    throw $e;
                }

                // recordAuthFailure already ran inside client handleAuthFailure
                $lastAuthException = $e;
                $previousCredentialId = (string) $credential->id;

                if (! $enabled || $attempt >= $maxRetries) {
                    throw $e;
                }

                $attempt++;
            }
        }
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
