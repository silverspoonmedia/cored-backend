<?php

namespace App\Domain\Service\Http\Resources;

use App\Domain\Service\Models\ServicePrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServicePrice
 */
class ServicePriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'currency' => $this->currency,
            'amount' => (float) $this->amount,
        ];
    }
}
