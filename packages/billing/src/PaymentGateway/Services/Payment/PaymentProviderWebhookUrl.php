<?php

namespace App\Domain\PaymentGateway\Services\Payment;

use Illuminate\Support\Facades\Route;

/**
 * Resolves the public webhook URL for a payment provider.
 *
 * iPaymu: sent as `unotify` on each checkout request.
 * Midtrans: register the same URL in the Midtrans Dashboard (Payment Notification URL).
 */
final class PaymentProviderWebhookUrl
{
    /**
     * @param  'midtrans'|'ipaymu'  $provider
     */
    public static function resolve(string $provider): string
    {
        $cfg = config("payment.gateways.{$provider}", []);
        $override = $cfg['notify_url'] ?? null;

        if (is_string($override) && $override !== '') {
            return $override;
        }

        $routeName = "api.v1.payments.providers.{$provider}.webhook";

        if (Route::has($routeName)) {
            return route($routeName, [], true);
        }

        return rtrim((string) config('app.url'), '/')."/api/v1/payments/providers/{$provider}/webhook";
    }
}
