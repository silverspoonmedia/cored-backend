<?php

namespace App\Domain\PaymentGateway\Http\Resources;

use App\Domain\PaymentGateway\Models\PaymentChannelRoute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentChannelRoute */
class PaymentChannelRouteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $gateway = $this->paymentGateway;

        return [
            'id' => $this->id,
            'gateway' => $gateway ? [
                'id' => $gateway->id,
                'name' => $gateway->name,
                'display_name' => $gateway->display_name,
            ] : null,
            'provider_payment_type' => $this->provider_payment_type,
            'provider_payment_channel' => $this->provider_payment_channel,
            'priority' => $this->priority,
            'is_active' => $this->is_active,
        ];
    }
}
