<?php

namespace App\Domain\Vtual\Support;

use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Support\Encryption\BlindIndex;

/**
 * Canonicalize / fingerprint / hint helpers for platform credential payloads.
 * Never log return values that include full secrets.
 */
final class PlatformCredentialSecrets
{
    /**
     * Build a validated payload array for storage (only allowed keys, trimmed strings).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function normalizePayload(PlatformCredentialType $type, array $input): array
    {
        $allowed = array_merge($type->requiredPayloadKeys(), $type->optionalPayloadKeys());
        $payload = [];

        foreach ($allowed as $key) {
            if (! array_key_exists($key, $input)) {
                continue;
            }
            $value = $input[$key];
            if ($value === null) {
                continue;
            }
            if (! is_string($value) && ! is_numeric($value)) {
                throw new VtualException(__('api/vtual.credential.payload_invalid'), 422);
            }
            $trimmed = trim((string) $value);
            if ($trimmed === '') {
                continue;
            }
            $payload[$key] = $trimmed;
        }

        foreach ($type->requiredPayloadKeys() as $required) {
            if (! isset($payload[$required]) || $payload[$required] === '') {
                throw new VtualException(__('api/vtual.credential.payload_missing', ['field' => $required]), 422);
            }
        }

        // Reject unexpected keys in input (defense; FormRequest should already filter).
        foreach (array_keys($input) as $key) {
            if (! in_array($key, $allowed, true)) {
                throw new VtualException(__('api/vtual.credential.payload_unknown_key', ['field' => $key]), 422);
            }
        }

        ksort($payload);

        return $payload;
    }

    /**
     * @param  array<string, string>  $payload
     */
    public static function fingerprint(PlatformCredentialType $type, array $payload): string
    {
        $canonical = $type->value.'|'.json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return BlindIndex::make($canonical);
    }

    /**
     * @param  array<string, string>  $payload
     */
    public static function hint(PlatformCredentialType $type, array $payload): string
    {
        $primary = $payload[$type->primarySecretKey()] ?? '';
        if ($primary === '') {
            return '';
        }

        $tail = mb_substr($primary, -4);

        return '…'.$tail;
    }
}
