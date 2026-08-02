<?php

namespace App\Domain\Vtual\Services\Probes;

use App\Domain\Vtual\Contracts\PlatformCredentialProbe;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Models\PlatformCredential;
use App\Domain\Vtual\Support\PlatformCredentialTestResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class TwitchOauthAppProbe implements PlatformCredentialProbe
{
    public function supports(PlatformCredential $credential): bool
    {
        $key = $credential->platform?->key;

        return $key === PlatformKey::Twitch
            && $credential->type === PlatformCredentialType::OauthApp;
    }

    public function probe(PlatformCredential $credential, array $secretPayload): PlatformCredentialTestResult
    {
        $clientId = $secretPayload['client_id'] ?? null;
        $clientSecret = $secretPayload['client_secret'] ?? null;
        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            return new PlatformCredentialTestResult(
                ok: false,
                message: __('api/vtual.credential.test_missing_secret'),
                platformKey: PlatformKey::Twitch->value,
            );
        }

        $timeout = (int) setting_config('vtual.credential_test.http_timeout', 20);

        $tokenResponse = Http::asForm()
            ->timeout($timeout)
            ->acceptJson()
            ->post('https://id.twitch.tv/oauth2/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'client_credentials',
            ]);

        $tokenBody = $tokenResponse->json() ?? [];
        $accessToken = $tokenBody['access_token'] ?? null;
        $expiresIn = $tokenBody['expires_in'] ?? null;

        if (! $tokenResponse->successful() || ! is_string($accessToken) || $accessToken === '') {
            $message = (string) ($tokenBody['message'] ?? $tokenBody['error'] ?? ('Twitch token HTTP '.$tokenResponse->status()));

            return new PlatformCredentialTestResult(
                ok: false,
                message: $message,
                platformKey: PlatformKey::Twitch->value,
            );
        }

        // Validate token can call Helix.
        $usersResponse = Http::timeout($timeout)
            ->acceptJson()
            ->withHeaders([
                'Client-Id' => $clientId,
                'Authorization' => 'Bearer '.$accessToken,
            ])
            ->get('https://api.twitch.tv/helix/users', [
                'login' => 'twitch',
            ]);

        if (! $usersResponse->successful()) {
            $userBody = $usersResponse->json() ?? [];
            $message = (string) ($userBody['message'] ?? ('Twitch Helix HTTP '.$usersResponse->status()));

            return new PlatformCredentialTestResult(
                ok: false,
                message: $message,
                platformKey: PlatformKey::Twitch->value,
            );
        }

        $expiresInInt = is_numeric($expiresIn) ? (int) $expiresIn : 3600;
        $now = Carbon::now();

        $runtime = [
            'access_token' => $accessToken,
            'token_type' => (string) ($tokenBody['token_type'] ?? 'bearer'),
            'expires_at' => $now->copy()->addSeconds(max(60, $expiresInInt))->toIso8601String(),
            'minted_at' => $now->toIso8601String(),
        ];

        return new PlatformCredentialTestResult(
            ok: true,
            message: __('api/vtual.credential.test_twitch_ok'),
            platformKey: PlatformKey::Twitch->value,
            runtime: $runtime,
        );
    }
}
