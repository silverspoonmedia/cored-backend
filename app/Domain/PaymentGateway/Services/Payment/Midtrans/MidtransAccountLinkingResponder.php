<?php

namespace App\Domain\PaymentGateway\Services\Payment\Midtrans;

// Internal
use Illuminate\Http\JsonResponse;

// Class
class MidtransAccountLinkingResponder
{
    /**
     * Open API / BI-SNAP style ACK for account linking notifications (responseCode 2008800).
     *
     * @see https://docs.midtrans.com/reference/account-linking-unlinking-notification
     */
    public static function success(): JsonResponse
    {
        return response()->json([
            'responseCode' => '2008800',
            'responseMessage' => 'Request has been processed successfully',
        ], 200, [
            'Content-Type' => 'application/json',
            'X-TIMESTAMP' => now()->toIso8601String(),
        ]);
    }
}
