<?php

namespace App\Domain\Microstock\Http\Resources;

use App\Domain\Microstock\Models\AiUsageEvent;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiUsageEvent */
class AdminBillingUsageEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $base = (new BillingUsageEventResource($this->resource))->toArray($request);

        return array_merge($base, [
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
        ]);
    }
}
