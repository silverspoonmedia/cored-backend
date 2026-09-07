<?php

namespace App\Domain\Vtual\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Guest-safe public event card. No UUID, no management internals.
 *
 * @mixin \App\Domain\Vtual\Models\VtualEvent
 */
class PublicEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'thumbnail_url' => $this->thumbnail_url,
            'url' => $this->url,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'timezone' => $this->timezone,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'is_featured' => $this->is_featured,
            'management' => $this->whenLoaded('management', fn (): ?array => $this->management === null ? null : [
                'slug' => $this->management->slug,
                'name' => $this->management->name,
            ]),
            'creator' => $this->whenLoaded('creator', fn (): ?array => $this->creator === null ? null : [
                'slug' => $this->creator->slug,
                'display_name' => $this->creator->display_name,
                'avatar_url' => $this->creator->avatar_url,
            ]),
        ];
    }
}
