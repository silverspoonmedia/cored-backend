<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Support\Api\ApiClient;

/**
 * @mixin ConsumesApi
 */
trait ResolvesPaymentCheckoutContext
{
    /**
     * @return array{paymentChannels: list<array<string, mixed>>, checkoutMode: 'channel'|'snap_fallback'}
     */
    protected function paymentCheckoutContext(): array
    {
        // Payment channels always live on the versioned API. Controllers such as
        // OrderController override api() to target api/playground — using that
        // client here would hit a non-existent route and force snap_fallback.
        $response = app(ApiClient::class)->get('payment-channels');

        if (! $response->successful()) {
            return [
                'paymentChannels' => [],
                'checkoutMode' => 'snap_fallback',
            ];
        }

        $channels = $response->json('data', []);

        if (! is_array($channels)) {
            $channels = [];
        }

        $channels = array_values($channels);

        return [
            'paymentChannels' => $channels,
            'checkoutMode' => $channels === [] ? 'snap_fallback' : 'channel',
        ];
    }
}
