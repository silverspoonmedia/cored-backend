<?php

namespace App\Domain\PaymentGateway\Services\Payment\Ipaymu;

// Class
/**
 * iPaymu "Callback Notify Simulation" / Public API v2 style: {@see https://documenter.getpostman.com/view/40296808/2sB3WtseBT}
 * — {@code X-Signature} header = {@code HMAC_SHA256( json_encode( payload after ksort, unset signature ), merchant VA )}.
 * The merchant VA (dashboard / {@code IPAYMU_VA_KEY}) is the HMAC **key**, not the API key.
 */
final class IpaymuSymmetricCallbackSignature
{
    /**
     * @param  array<string, mixed>  $payload  Decoded JSON body (associative).
     */
    public static function matchesSortedPayloadHmac(array $payload, string $receivedHex, string $merchantVirtualAccount): bool
    {
        $receivedHex = trim($receivedHex);
        $merchantVirtualAccount = trim($merchantVirtualAccount);

        if ($receivedHex === '' || $merchantVirtualAccount === '') {
            return false;
        }

        $work = $payload;
        unset($work['signature']);

        ksort($work);

        foreach ([0, JSON_UNESCAPED_SLASHES] as $flags) {
            $json = json_encode($work, $flags);

            if (! is_string($json)) {
                continue;
            }

            $calculated = hash_hmac('sha256', $json, $merchantVirtualAccount);

            if (hash_equals($calculated, $receivedHex)) {
                return true;
            }
        }

        return false;
    }
}
