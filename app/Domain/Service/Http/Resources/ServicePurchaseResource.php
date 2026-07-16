<?php

namespace App\Domain\Service\Http\Resources;

use App\Domain\PaymentGateway\Http\Resources\InvoiceResource;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\ServicePurchase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServicePurchase
 */
class ServicePurchaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'service_id' => $this->service_id,
            'display_currency' => $this->display_currency,
            'display_amount' => (float) $this->display_amount,
            'settlement_currency' => $this->settlement_currency,
            'settlement_amount' => (float) $this->settlement_amount,
            'fx_rate' => $this->fx_rate !== null ? (float) $this->fx_rate : null,
            'fx_provider' => $this->fx_provider,
            'fx_fetched_at' => $this->fx_fetched_at?->toIso8601String(),
            'fx_rate_snapshot_id' => $this->fx_rate_snapshot_id,
            'period_start' => $this->period_start?->toIso8601String(),
            'period_end' => $this->period_end?->toIso8601String(),
            'status' => $status instanceof PurchaseStatus ? $status->value : (string) $status,
            'service' => new ServiceResource($this->whenLoaded('service')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            'entitlement' => $this->when(
                $this->relationLoaded('entitlement') && $this->entitlement !== null,
                fn () => new ServiceEntitlementResource($this->entitlement),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
