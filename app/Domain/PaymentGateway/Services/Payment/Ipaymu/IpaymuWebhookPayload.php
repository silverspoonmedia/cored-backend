<?php

namespace App\Domain\PaymentGateway\Services\Payment\Ipaymu;

// Class
/**
 * Normalizes iPaymu notifyUrl JSON keys (docs and samples vary).
 */
final class IpaymuWebhookPayload
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function referenceId(array $payload): ?string
    {
        foreach (['referenceId', 'ReferenceId', 'reference_id'] as $key) {
            $v = $payload[$key] ?? null;
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function transactionId(array $payload): ?string
    {
        foreach (['transactionId', 'TransactionId', 'trx_id', 'TrxId', 'id'] as $key) {
            $v = $payload[$key] ?? null;
            if (is_numeric($v) && (string) $v !== '') {
                return (string) $v;
            }
        }

        return null;
    }
}
