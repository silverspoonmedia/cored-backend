<?php

namespace App\Domain\Vtual\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Guest-safe public content card. No UUID, no claim URLs.
 *
 * @mixin \App\Domain\Vtual\Models\Content
 */
class PublicContentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'title' => $this->title,
            'thumbnail_url' => $this->thumbnail_url,
            'url' => $this->url,
            'published_at' => $this->published_at?->toIso8601String(),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            // Region hint: expose, never hide. Null = unrestricted.
            'region_allowed' => $this->region_allowed,
            'region_blocked' => $this->region_blocked,
            'platform_key' => $this->platform?->key instanceof \BackedEnum
                ? $this->platform->key->value
                : $this->platform?->key,
            'creator' => $this->whenLoaded('creator', fn (): array => [
                'slug' => $this->creator->slug,
                'display_name' => $this->creator->display_name,
                'avatar_url' => $this->creator->avatar_url,
            ]),
            'channel' => $this->whenLoaded('channel', fn (): array => [
                'display_name' => $this->channel->display_name,
                'avatar_url' => $this->channel->avatar_url,
            ]),
        ];
    }
}
