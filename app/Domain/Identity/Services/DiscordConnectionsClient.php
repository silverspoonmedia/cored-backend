<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Exceptions\IdentityOAuthException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Discord user connections API (YouTube / Twitch links on Discord profile).
 */
class DiscordConnectionsClient
{
    /**
     * @return list<array{type: string, id: string, name: string, verified: bool}>
     */
    public function fetchConnections(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(15)
                ->get('https://discord.com/api/users/@me/connections');
        } catch (RequestException $e) {
            throw new IdentityOAuthException(__('api/identity.oauth.connections_failed'), 502, $e);
        }

        if (! $response->successful()) {
            throw new IdentityOAuthException(__('api/identity.oauth.connections_failed'), 502);
        }

        /** @var list<array<string, mixed>> $raw */
        $raw = $response->json() ?? [];

        return $this->normalize($raw);
    }

    /**
     * @param  list<array<string, mixed>>  $raw
     * @return list<array{type: string, id: string, name: string, verified: bool}>
     */
    public function normalize(array $raw): array
    {
        $normalized = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = (string) ($item['type'] ?? '');
            if ($type !== 'youtube' && $type !== 'twitch') {
                continue;
            }

            $id = (string) ($item['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $normalized[] = [
                'type' => $type,
                'id' => $id,
                'name' => (string) ($item['name'] ?? ''),
                'verified' => (bool) ($item['verified'] ?? false),
            ];
        }

        return $normalized;
    }
}
