<?php

namespace App\Domain\Ai\Support;

use App\Domain\Ai\Exceptions\AiException;
use App\Support\Encryption\BlindIndex;

/**
 * Canonicalize / fingerprint / hint helpers for AI connection secrets.
 * Never log return values that include the full key.
 */
final class AiConnectionSecrets
{
    /**
     * Build a validated payload array for storage.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function normalizePayload(array $input): array
    {
        $allowed = self::allowedKeys();
        $payload = [];

        foreach (array_keys($input) as $key) {
            if (! in_array($key, $allowed, true)) {
                throw new AiException(__('api/ai.connection.payload_unknown_key', ['field' => $key]), 422);
            }
        }

        foreach ($allowed as $key) {
            $value = $input[$key] ?? null;
            if ($value === null) {
                continue;
            }

            if (! is_string($value) && ! is_numeric($value)) {
                throw new AiException(__('api/ai.connection.payload_invalid'), 422);
            }

            $trimmed = trim((string) $value);
            if ($trimmed === '') {
                continue;
            }

            $payload[$key] = $trimmed;
        }

        if (! isset($payload['api_key'])) {
            throw new AiException(__('api/ai.connection.payload_missing', ['field' => 'api_key']), 422);
        }

        ksort($payload);

        return $payload;
    }

    /**
     * @return list<string>
     */
    public static function allowedKeys(): array
    {
        return ['api_key'];
    }

    /**
     * @param  array<string, string>  $payload
     */
    public static function fingerprint(array $payload): string
    {
        $canonical = 'ai_connection|'.json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return BlindIndex::make($canonical);
    }

    /**
     * @param  array<string, string>  $payload
     */
    public static function hint(array $payload): string
    {
        $primary = $payload['api_key'] ?? '';
        if ($primary === '') {
            return '';
        }

        return '…'.mb_substr($primary, -4);
    }
}
