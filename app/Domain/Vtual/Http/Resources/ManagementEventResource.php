<?php

namespace App\Domain\Vtual\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin-facing event row for the management events surface (SPA
 * /apps/vtual/events). Includes UUIDs + management/creator refs — unlike the
 * guest-safe PublicEventResource.
 *
 * @mixin \App\Domain\Vtual\Models\VtualEvent
 */
class ManagementEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'management_id' => (string) $this->management_id,
            'creator_id' => $this->creator_id !== null ? (string) $this->creator_id : null,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'thumbnail_url' => $this->thumbnail_url,
            'url' => $this->url,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'timezone' => $this->timezone,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'is_featured' => $this->is_featured,
            'management' => $this->whenLoaded('management', fn (): ?array => $this->management === null ? null : [
                'id' => (string) $this->management->id,
                'slug' => $this->management->slug,
                'name' => $this->management->name,
            ]),
            'creator' => $this->whenLoaded('creator', fn (): ?array => $this->creator === null ? null : [
                'id' => (string) $this->creator->id,
                'slug' => $this->creator->slug,
                'display_name' => $this->creator->display_name,
                'avatar_url' => $this->creator->avatar_url,
            ]),
        ];
    }
}
