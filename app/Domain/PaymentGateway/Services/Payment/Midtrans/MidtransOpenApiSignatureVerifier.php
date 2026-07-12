<?php

namespace App\Domain\PaymentGateway\Services\Payment\Midtrans;

// Internal
use Illuminate\Http\Request;

// Class
/**
 * Verifies Midtrans Open API / BI-SNAP asymmetric webhook signatures (X-SIGNATURE header).
 *
 * stringToSign = HTTPMethod + ":" + EndpointUrl + ":" + Lowercase(HexEncode(SHA-256(minify(RequestBody)))) + ":" + TimeStamp
 *
 * @see https://docs.midtrans.com/reference/account-linking-unlinking-notification
 * Matches the string-to-sign layout used by Midtrans SnapBi webhook verification (midtrans-php).
 */
class MidtransOpenApiSignatureVerifier
{
    public const ACCOUNT_LINKING_PATH = '/v1.0/registration-account/notify';

    /**
     * @param  non-empty-string|null  $notificationUrlPath  Preferred API path (Midtrans usually signs this, e.g. /v1.0/...).
     */
    public static function verify(Request $request, ?string $notificationUrlPath = null, ?string $publicKeyPem = null): bool
    {
        $publicKeyPem ??= config('payment.gateways.midtrans.open_api_public_key');

        if (! is_string($publicKeyPem) || trim($publicKeyPem) === '') {
            return false;
        }

        $publicKeyPem = trim($publicKeyPem);

        $signature = self::headerLine($request, 'X-Signature');
        $timestamp = self::headerLine($request, 'X-Timestamp');

        if ($signature === null || $timestamp === null) {
            return false;
        }

        $binarySig = base64_decode(preg_replace('/\s+/', '', $signature), true);

        if ($binarySig === false || $binarySig === '') {
            return false;
        }

        $rawBody = $request->getContent();

        foreach (self::endpointPathCandidates($request, $notificationUrlPath) as $endpointPath) {
            foreach (['POST', 'post'] as $method) {
                foreach (self::bodySha256HexCandidates($rawBody) as $bodyHashHex) {
                    $stringToSign = $method.':'.$endpointPath.':'.$bodyHashHex.':'.$timestamp;

                    if (self::opensslVerifyRsaSha256($stringToSign, $binarySig, $publicKeyPem)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Midtrans signs with the logical Open API path (/v1.0/...). Some proxies/tests may expect the full HTTP path
     * (e.g. /api/core/v1/.../webhook/v1.0/registration-account/notify) — try both.
     *
     * @return list<string>
     */
    protected static function endpointPathCandidates(Request $request, ?string $primaryPath): array
    {
        $configured = config('payment.gateways.midtrans.open_api_endpoint_path');

        $paths = [];

        if (is_string($configured) && $configured !== '') {
            $paths[] = self::normalizeEndpointPath($configured);
        }

        $paths[] = self::normalizeEndpointPath($primaryPath ?? self::ACCOUNT_LINKING_PATH);

        $pathInfo = $request->getPathInfo();

        if (is_string($pathInfo) && $pathInfo !== '') {
            $paths[] = self::normalizeEndpointPath($pathInfo);
        }

        return array_values(array_unique(array_filter($paths)));
    }

    protected static function normalizeEndpointPath(string $path): string
    {
        $path = trim($path);

        return str_starts_with($path, '/') ? $path : '/'.$path;
    }

    /**
     * @return list<string>
     */
    protected static function bodySha256HexCandidates(string $rawBody): array
    {
        $hashes = [];

        $push = static function (string $s) use (&$hashes): void {
            $hashes[] = hash('sha256', $s);
        };

        $push($rawBody);

        if ($rawBody !== '' && $rawBody !== trim($rawBody)) {
            $push(trim($rawBody));
        }

        $decoded = json_decode($rawBody, true);

        if (is_array($decoded)) {
            $push(json_encode($decoded));
            $push(json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            $sorted = $decoded;
            self::ksortRecursive($sorted);
            $push(json_encode($sorted));
            $push(json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        return array_values(array_unique($hashes));
    }

    protected static function ksortRecursive(array &$array): void
    {
        ksort($array);

        foreach ($array as &$value) {
            if (is_array($value)) {
                self::ksortRecursive($value);
            }
        }
    }

    protected static function opensslVerifyRsaSha256(string $data, string $signatureBinary, string $publicKeyPem): bool
    {
        $pem = self::normalizePublicKeyPem($publicKeyPem);
        $key = openssl_pkey_get_public($pem);

        if ($key === false) {
            return false;
        }

        try {
            $result = openssl_verify($data, $signatureBinary, $key, OPENSSL_ALGO_SHA256);

            return $result === 1;
        } finally {
            if (PHP_VERSION_ID < 80000) {
                openssl_free_key($key);
            }
        }
    }

    protected static function normalizePublicKeyPem(string $key): string
    {
        $key = trim($key);

        if (str_contains($key, 'BEGIN')) {
            return $key;
        }

        $b64 = preg_replace('/\s+/', '', $key);
        $chunks = str_split($b64, 64);

        return "-----BEGIN PUBLIC KEY-----\n".implode("\n", $chunks)."\n-----END PUBLIC KEY-----";
    }

    protected static function headerLine(Request $request, string $name): ?string
    {
        $value = $request->header($name);

        if (! is_string($value) || $value === '') {
            return null;
        }

        return trim($value);
    }
}
