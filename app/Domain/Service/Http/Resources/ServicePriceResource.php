<?php

namespace App\Domain\Service\Http\Resources;

use App\Domain\Service\Models\ServicePrice;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServicePrice
 */
class ServicePriceResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'currency' => $this->currency,
            ...$this->moneyFields('amount', $this->amount, $this->currency, asFloat: true),
        ];
    }
}
