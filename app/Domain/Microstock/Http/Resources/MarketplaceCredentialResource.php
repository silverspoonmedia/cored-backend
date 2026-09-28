<?php

namespace App\Domain\Microstock\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceCredentialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'marketplace' => $this->marketplace,
            'label' => $this->label,
            'status' => $this->status?->value,
            'last_error' => $this->last_error,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'last_verified_at' => $this->last_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
