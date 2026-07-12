<?php

namespace App\Domain\PaymentGateway\Services\Payment\Midtrans;

// Class
class MidtransNotificationPayloadType
{
    /**
     * Account linking / unlinking (Open API). Body uses additionalInfo.* — not the Core API payment signature.
     */
    public static function isAccountLinking(array $payload): bool
    {
        $info = $payload['additionalInfo'] ?? null;

        if (! is_array($info)) {
            return false;
        }

        return isset($info['accountStatus'], $info['paymentType']);
    }

    /**
     * Recurring / subscription HTTP notifications (no signature_key; different shapes).
     *
     * @see https://docs.midtrans.com/reference/http-notification
     */
    public static function isSubscription(array $payload): bool
    {
        if (isset($payload['signature_key'], $payload['order_id'])) {
            return false;
        }

        if (isset($payload['event_name']) && is_string($payload['event_name'])
            && str_starts_with($payload['event_name'], 'subscription.')) {
            return true;
        }

        if (isset($payload['subscription']) && is_array($payload['subscription'])) {
            return true;
        }

        return isset($payload['token'], $payload['status'], $payload['schedule']) && ! isset($payload['order_id']);
    }
}
