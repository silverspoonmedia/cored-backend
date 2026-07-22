<?php

namespace App\Domain\Vtual\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Vtual\Models\Channel */
class ChannelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'creator_id' => $this->creator_id,
            'platform_id' => $this->platform_id,
            'external_id' => $this->external_id,
            'handle' => $this->handle,
            'display_name' => $this->display_name,
            'url' => $this->url,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'platform' => $this->whenLoaded('platform', fn () => new PlatformResource($this->platform)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
