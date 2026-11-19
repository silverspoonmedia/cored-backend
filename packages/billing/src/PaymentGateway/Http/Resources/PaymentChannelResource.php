<?php

namespace App\Domain\PaymentGateway\Http\Resources;

use App\Domain\PaymentGateway\Models\PaymentChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentChannel */
class PaymentChannelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'code' => $this->code,
            'display_name' => $this->display_name,
            'kind' => $this->kind->value,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'cheap_alt_code' => $this->cheap_alt_code,
            'routing_type' => $this->routing_type->value,
            'policy_key' => $this->policy_key,
        ];

        if ($request->input('scope') === 'admin' && $this->relationLoaded('routes')) {
            $payload['routes'] = PaymentChannelRouteResource::collection($this->routes)->resolve();
        }

        $quote = $this->checkout_quote ?? null;

        if (is_array($quote)) {
            $payload = array_merge($payload, $quote);
        }

        return $payload;
    }
}
