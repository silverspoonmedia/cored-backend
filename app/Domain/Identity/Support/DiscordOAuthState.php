<?php

namespace App\Domain\Identity\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Signed OAuth state for Discord connect / claim flows.
 */
final class DiscordOAuthState
{
    /**
     * @return array{user_id: string, intent: string, slug?: string, channel_id?: string, claim_id?: string, return_to?: string}
     */
    public static function encode(
        string $userId,
        string $intent,
        ?string $slug = null,
        ?string $channelId = null,
        ?string $claimId = null,
        ?string $returnTo = null,
    ): string {
        return Crypt::encrypt([
            'user_id' => $userId,
            'intent' => $intent,
            'slug' => $slug,
            'channel_id' => $channelId,
            'claim_id' => $claimId,
            'return_to' => $returnTo,
            'exp' => now()->addMinutes(15)->timestamp,
        ]);
    }

    /**
     * @return array{user_id: string, intent: string, slug?: string, channel_id?: string, claim_id?: string, return_to?: string}
     */
    public static function decode(string $state): array
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = Crypt::decrypt($state);
        } catch (DecryptException) {
            throw new \InvalidArgumentException('Invalid OAuth state.');
        }

        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp < now()->timestamp) {
            throw new \InvalidArgumentException('OAuth state expired.');
        }

        $userId = (string) ($payload['user_id'] ?? '');
        $intent = (string) ($payload['intent'] ?? '');
        if ($userId === '' || $intent === '') {
            throw new \InvalidArgumentException('Invalid OAuth state payload.');
        }

        $returnTo = isset($payload['return_to']) ? (string) $payload['return_to'] : null;

        return [
            'user_id' => $userId,
            'intent' => $intent,
            'slug' => isset($payload['slug']) ? (string) $payload['slug'] : null,
            'channel_id' => isset($payload['channel_id']) ? (string) $payload['channel_id'] : null,
            'claim_id' => isset($payload['claim_id']) ? (string) $payload['claim_id'] : null,
            'return_to' => $returnTo !== '' ? $returnTo : null,
        ];
    }
}
