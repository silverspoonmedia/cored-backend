<?php

namespace App\Domain\Inference\Http\Resources;

use App\Domain\Inference\Models\ProviderModelRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProviderModelRate */
class ProviderModelRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'model_card_id' => $this->model_card_id,
            'input_per_million_usd' => (float) $this->input_per_million_usd,
            'output_per_million_usd' => (float) $this->output_per_million_usd,
            'model_card' => $this->whenLoaded(
                'modelCard',
                fn () => new AiModelCardResource($this->modelCard),
            ),
        ];
    }
}
