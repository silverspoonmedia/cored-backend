<?php

namespace App\Domain\PaymentGateway\Services\Payment\Midtrans;

// Class
class MidtransSignatureVerifier
{
    /**
     * Verify HTTP notification authenticity (SHA512 over order_id + status_code + gross_amount + server_key).
     *
     * @param  array<string, mixed>  $payload  Decoded JSON body from Midtrans
     */
    public static function isValid(array $payload, string $serverKey): bool
    {
        $signatureKey = $payload['signature_key'] ?? null;
        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $grossAmount = $payload['gross_amount'] ?? null;

        if (! is_string($signatureKey) || $signatureKey === ''
            || ! is_string($orderId) || $orderId === ''
            || $statusCode === null || $statusCode === ''
            || $grossAmount === null || $grossAmount === '') {
            return false;
        }

        $statusCode = (string) $statusCode;
        $grossAmount = (string) $grossAmount;

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        return hash_equals($expected, $signatureKey);
    }
}
