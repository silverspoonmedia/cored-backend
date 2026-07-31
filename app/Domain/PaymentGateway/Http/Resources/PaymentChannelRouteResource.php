<?php

namespace App\Domain\PaymentGateway\Http\Resources;

use App\Domain\PaymentGateway\Models\PaymentChannelRoute;
use App\Domain\PaymentGateway\Support\Routing\PaymentChannelFeeCalculator;
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
        $calculator = app(PaymentChannelFeeCalculator::class);

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
            'route_role' => $this->route_role?->value ?? $this->route_role,
            'settlement_days' => $this->settlement_days,
            'settlement_label' => $this->settlement_days !== null ? 'H+'.$this->settlement_days : null,
            'fee_flat' => $this->fee_flat,
            'fee_percent' => $this->fee_percent !== null ? (float) $this->fee_percent : null,
            'fee_label' => $calculator->formatFeeLabel($this->resource),
            'fee_vat_inclusive' => $this->fee_vat_inclusive,
            'policy_tag' => $this->policy_tag,
            'is_policy_managed' => $this->is_policy_managed,
            'is_active' => $this->is_active,
        ];
    }
}
