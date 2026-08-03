<?php

namespace App\Domain\Vtual\Services\Probes;

use App\Domain\Vtual\Contracts\PlatformCredentialProbe;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Models\PlatformCredential;
use App\Domain\Vtual\Support\PlatformCredentialTestResult;
use Illuminate\Support\Facades\Http;

class YoutubeApiKeyProbe implements PlatformCredentialProbe
{
    public function supports(PlatformCredential $credential): bool
    {
        $key = $credential->platform?->key;

        return $key === PlatformKey::Youtube
            && $credential->type === PlatformCredentialType::ApiKey;
    }

    public function probe(PlatformCredential $credential, array $secretPayload): PlatformCredentialTestResult
    {
        $apiKey = $secretPayload['api_key'] ?? null;
        if (! is_string($apiKey) || $apiKey === '') {
            return new PlatformCredentialTestResult(
                ok: false,
                message: __('api/vtual.credential.test_missing_secret'),
                platformKey: PlatformKey::Youtube->value,
            );
        }

        $channelId = (string) setting_config('vtual.credential_test.youtube_probe_channel_id');
        $timeout = (int) setting_config('vtual.credential_test.http_timeout', 20);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'id',
                'id' => $channelId,
                'key' => $apiKey,
            ]);

        $body = $response->json() ?? [];

        if (! $response->successful()) {
            $message = is_array($body['error'] ?? null)
                ? (string) ($body['error']['message'] ?? 'YouTube API error')
                : 'YouTube API HTTP '.$response->status();

            return new PlatformCredentialTestResult(
                ok: false,
                message: $message,
                platformKey: PlatformKey::Youtube->value,
            );
        }

        $items = $body['items'] ?? [];
        if (! is_array($items) || $items === []) {
            return new PlatformCredentialTestResult(
                ok: false,
                message: __('api/vtual.credential.test_youtube_empty'),
                platformKey: PlatformKey::Youtube->value,
            );
        }

        return new PlatformCredentialTestResult(
            ok: true,
            message: __('api/vtual.credential.test_youtube_ok'),
            platformKey: PlatformKey::Youtube->value,
            runtime: null,
        );
    }
}
