<?php

namespace App\Domain\Vtual\Services\Probes;

use App\Domain\Vtual\Contracts\PlatformCredentialProbe;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Models\PlatformCredential;
use App\Domain\Vtual\Support\PlatformCredentialTestResult;
use Illuminate\Support\Facades\Http;

/**
 * Admin-pasted bearer credentials — validate only, do not mint or rewrite runtime.
 */
class BearerTokenProbe implements PlatformCredentialProbe
{
    public function supports(PlatformCredential $credential): bool
    {
        return $credential->type === PlatformCredentialType::Bearer;
    }

    public function probe(PlatformCredential $credential, array $secretPayload): PlatformCredentialTestResult
    {
        $accessToken = $secretPayload['access_token'] ?? null;
        if (! is_string($accessToken) || $accessToken === '') {
            return new PlatformCredentialTestResult(
                ok: false,
                message: __('api/vtual.credential.test_missing_secret'),
                platformKey: (string) ($credential->platform?->key?->value ?? 'unknown'),
            );
        }

        $platformKey = $credential->platform?->key;
        $timeout = (int) config('vtual.credential_test.http_timeout', 20);

        if ($platformKey === PlatformKey::Twitch) {
            $clientId = $secretPayload['client_id'] ?? null;
            // Bearer-only may not include client_id; skip Helix if missing.
            if (! is_string($clientId) || $clientId === '') {
                return new PlatformCredentialTestResult(
                    ok: true,
                    message: __('api/vtual.credential.test_bearer_ok_no_probe'),
                    platformKey: PlatformKey::Twitch->value,
                    runtime: null,
                );
            }

            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withHeaders([
                    'Client-Id' => $clientId,
                    'Authorization' => 'Bearer '.$accessToken,
                ])
                ->get('https://api.twitch.tv/helix/users', ['login' => 'twitch']);

            if (! $response->successful()) {
                $body = $response->json() ?? [];

                return new PlatformCredentialTestResult(
                    ok: false,
                    message: (string) ($body['message'] ?? ('Twitch Helix HTTP '.$response->status())),
                    platformKey: PlatformKey::Twitch->value,
                );
            }

            return new PlatformCredentialTestResult(
                ok: true,
                message: __('api/vtual.credential.test_bearer_ok'),
                platformKey: PlatformKey::Twitch->value,
                runtime: null,
            );
        }

        // Other platforms: presence of non-empty token is enough for foundation.
        return new PlatformCredentialTestResult(
            ok: true,
            message: __('api/vtual.credential.test_bearer_ok_no_probe'),
            platformKey: (string) ($platformKey?->value ?? 'unknown'),
            runtime: null,
        );
    }
}
