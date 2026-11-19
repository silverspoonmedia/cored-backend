<?php

namespace App\Domain\PaymentGateway\Http\Resources;

use App\Domain\PaymentGateway\Models\PaymentChannelRouteSnapshot;
use App\Domain\PaymentGateway\Support\Routing\PaymentChannelFeeCalculator;
use App\Support\Presentation\DatePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentChannelRouteSnapshot */
class PaymentChannelRouteSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $gateway = $this->paymentGateway;
        $calculator = app(PaymentChannelFeeCalculator::class);
        [$capturedIso, $capturedFormatted, $capturedHuman] = DatePresenter::pack($this->captured_at);

        return [
            'id' => $this->id,
            'payment_channel_code' => $this->payment_channel_code,
            'gateway' => $gateway ? [
                'id' => $gateway->id,
                'name' => $gateway->name,
                'display_name' => $gateway->display_name,
            ] : null,
            'provider_payment_type' => $this->provider_payment_type,
            'provider_payment_channel' => $this->provider_payment_channel,
            'route_role' => $this->route_role->value ?? $this->route_role,
            'settlement_days' => $this->settlement_days,
            'settlement_label' => $this->settlement_days !== null ? 'H+'.$this->settlement_days : null,
            'fee_flat' => $this->fee_flat,
            'fee_percent' => $this->fee_percent !== null ? (float) $this->fee_percent : null,
            'fee_label' => $calculator->formatFeeLabelFromSnapshot($this->resource),
            'fee_vat_inclusive' => $this->fee_vat_inclusive,
            'policy_tag' => $this->policy_tag,
            'captured_at' => $capturedIso,
            'captured_at_formatted' => $capturedFormatted,
            'captured_at_human' => $capturedHuman,
        ];
    }
}
