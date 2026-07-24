<?php

namespace App\Domain\Service\Http\Resources;

use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceEntitlement
 */
class ServiceEntitlementResource extends JsonResource
{
    use FormatsPresentation;

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
            'status' => $status instanceof EntitlementStatus ? $status->value : (string) $status,
            'quantity' => max(1, (int) $this->quantity),
            ...$this->dateFields('starts_at', $this->starts_at),
            ...$this->dateFields('ends_at', $this->ends_at),
            ...$this->dateFields('grace_ends_at', $this->grace_ends_at),
            'source_purchase_id' => $this->source_purchase_id,
            'is_permanent' => $this->ends_at === null && $status === EntitlementStatus::Active,
        ];
    }
}
