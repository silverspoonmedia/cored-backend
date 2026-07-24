<?php

namespace App\Domain\Service\Http\Resources;

use App\Domain\PaymentGateway\Http\Resources\InvoiceResource;
use App\Domain\Service\Enums\PurchaseChangeType;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\ServicePurchase;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServicePurchase
 */
class ServicePurchaseResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status;
        $changeType = $this->change_type;
        $displayCurrency = $this->display_currency;
        $settlementCurrency = $this->settlement_currency;
        $chargeAmount = $this->charge_amount !== null
            ? (float) $this->charge_amount
            : (float) $this->display_amount;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'service_id' => $this->service_id,
            'display_currency' => $displayCurrency,
            ...$this->moneyFields('display_amount', $this->display_amount, $displayCurrency, asFloat: true),
            ...$this->moneyFields(
                'list_amount',
                $this->list_amount !== null ? (float) $this->list_amount : null,
                $displayCurrency,
                asFloat: true,
            ),
            ...$this->moneyFields('credit_amount', $this->credit_amount ?? 0, $displayCurrency, asFloat: true),
            ...$this->moneyFields('charge_amount', $chargeAmount, $displayCurrency, asFloat: true),
            'surplus_days' => (int) ($this->surplus_days ?? 0),
            'change_type' => $changeType instanceof PurchaseChangeType
                ? $changeType->value
                : $changeType,
            'previous_entitlement_id' => $this->previous_entitlement_id,
            'settlement_currency' => $settlementCurrency,
            ...$this->moneyFields('settlement_amount', $this->settlement_amount, $settlementCurrency, asFloat: true),
            'fx_rate' => $this->fx_rate !== null ? (float) $this->fx_rate : null,
            'fx_provider' => $this->fx_provider,
            ...$this->dateFields('fx_fetched_at', $this->fx_fetched_at),
            'fx_rate_snapshot_id' => $this->fx_rate_snapshot_id,
            ...$this->dateFields('period_start', $this->period_start),
            ...$this->dateFields('period_end', $this->period_end),
            'status' => $status instanceof PurchaseStatus ? $status->value : (string) $status,
            'service' => new ServiceResource($this->whenLoaded('service')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            'entitlement' => $this->when(
                $this->relationLoaded('entitlement') && $this->entitlement !== null,
                fn () => new ServiceEntitlementResource($this->entitlement),
            ),
            ...$this->dateFields('created_at', $this->created_at),
            ...$this->dateFields('updated_at', $this->updated_at),
        ];
    }
}
