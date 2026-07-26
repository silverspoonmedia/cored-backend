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
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
