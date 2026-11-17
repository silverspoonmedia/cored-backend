<?php

namespace App\Domain\Inference\Http\Resources;

use App\Domain\Inference\Models\AiModelCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiModelCard */
class AiModelCardDetailResource extends JsonResource
{
    /**
     * @param  array<string, mixed>  $providerRate
     * @param  array<string, mixed>  $usageSummary
     */
    public function __construct(
        AiModelCard $resource,
        private readonly array $providerRate = [],
        private readonly array $usageSummary = [],
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(
            (new AiModelCardResource($this->resource))->toArray($request),
            [
                'provider_rate' => $this->providerRate,
                'usage_summary' => $this->usageSummary,
            ],
        );
    }
}
