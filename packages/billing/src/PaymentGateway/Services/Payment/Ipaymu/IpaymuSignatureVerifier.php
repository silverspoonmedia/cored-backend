<?php

namespace App\Domain\PaymentGateway\Services\Payment\Ipaymu;

use iPaymu\Traits\CurlTrait;

// Class
/**
 * Verifies notifyUrl callbacks using the same rules as {@see CurlTrait::genSignature}:
 * HMAC-SHA256(apiKey, "POST:{va}:{lowercase(sha256(body))}:{apiKey}").
 *
 * iPaymu may sign either the exact wire body or a compact JSON form (see {@see self::bodyCandidatesForSigning}).
 */
final class IpaymuSignatureVerifier
{
    public static function signatureForBody(string $va, string $bodyMaterial, string $apiKey): string
    {
        $bodyHash = strtolower(hash('sha256', $bodyMaterial));
        $stringToSign = 'POST:'.$va.':'.$bodyHash.':'.$apiKey;

        return hash_hmac('sha256', $stringToSign, $apiKey);
    }

    public static function isValid(string $rawBody, string $va, string $signature, string $apiKey): bool
    {
        $signature = trim($signature);
        $va = trim($va);
        $apiKey = trim($apiKey);

        if ($signature === '' || $va === '' || $apiKey === '') {
            return false;
        }

        foreach (self::bodyCandidatesForSigning($rawBody) as $bodyMaterial) {
            $expected = self::signatureForBody($va, $bodyMaterial, $apiKey);
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function bodyCandidatesForSigning(string $rawBody): array
    {
        $candidates = [];

        if ($rawBody === '') {
            return [''];
        }

        $candidates[] = $rawBody;

        $bomStripped = preg_replace('/^\xEF\xBB\xBF/', '', $rawBody) ?? $rawBody;
        if ($bomStripped !== '' && $bomStripped !== $rawBody) {
            $candidates[] = $bomStripped;
        }

        $trimmed = trim($rawBody);
        if ($trimmed !== '' && $trimmed !== $rawBody && $trimmed !== $bomStripped) {
            $candidates[] = $trimmed;
        }

        $decoded = json_decode($rawBody, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $compact = json_encode($decoded, JSON_UNESCAPED_SLASHES);
            if (is_string($compact)) {
                $candidates[] = $compact;
            }
        }

        $unique = [];
        foreach ($candidates as $c) {
            $unique[$c] = true;
        }

        return array_keys($unique);
    }
}
