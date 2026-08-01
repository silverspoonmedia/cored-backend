<?php

namespace App\Domain\Inference\Http\Resources;

use App\Domain\Inference\Models\InferenceProvider;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InferenceProvider */
class InferenceProviderResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'model_rates_count' => $this->whenCounted('modelRates'),
            'model_rates' => ProviderModelRateResource::collection($this->whenLoaded('modelRates')),
            ...$this->dateFields('created_at', $this->created_at),
            ...$this->dateFields('updated_at', $this->updated_at),
        ];
    }
}
