<?php

namespace App\Domain\Service\Http\Resources;

use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Enums\ServiceStatus;
use App\Domain\Service\Models\Service;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Service
 */
class ServiceResource extends JsonResource
{
    use FormatsPresentation;

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
            'plan_kind' => $this->plan_kind instanceof PlanKind
                ? $this->plan_kind->value
                : $this->plan_kind,
            ...$this->byteFields('storage_bytes', $this->storage_bytes),
            'status' => $status instanceof ServiceStatus ? $status->value : (string) $status,
            'prices' => ServicePriceResource::collection($this->whenLoaded('prices')),
            ...$this->dateFields('created_at', $this->created_at),
            ...$this->dateFields('updated_at', $this->updated_at),
        ];
    }
}
