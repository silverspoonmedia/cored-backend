<?php

namespace App\Domain\Service\Http\Resources;

use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\ServiceStatus;
use App\Domain\Service\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Service
 */
class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status;
        $interval = $this->billing_interval;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'billing_interval' => $interval instanceof BillingInterval ? $interval->value : (string) $interval,
            'fulfillment_target' => $this->fulfillment_target,
            'entitlement' => $this->entitlement,
            'status' => $status instanceof ServiceStatus ? $status->value : (string) $status,
            'prices' => ServicePriceResource::collection($this->whenLoaded('prices')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
