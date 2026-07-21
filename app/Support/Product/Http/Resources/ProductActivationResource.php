<?php

namespace App\Support\Product\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductActivationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'product_key' => $this->product_key,
            'activation_type' => $this->whenNotNull($this->activation_type?->value),
            'state' => $this->state->value,
            'provisioning_payload' => $this->provisioning_payload,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'expired_at' => $this->expired_at?->toIso8601String(),
            'source' => $this->source,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
        ];
    }
}
