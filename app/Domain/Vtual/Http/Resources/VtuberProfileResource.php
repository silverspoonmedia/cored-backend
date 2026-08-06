<?php

namespace App\Domain\Vtual\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Vtual\Models\Creator */
class VtuberProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'profile_url' => route('vtual.vtuber.show', $this->slug),
            'display_name' => $this->display_name,
            'bio' => $this->bio,
            'avatar_url' => $this->avatar_url,
            'channels' => $this->whenLoaded(
                'channels',
                fn () => ChannelResource::collection($this->channels),
            ),
            'contents' => $this->relationLoaded('profileContents')
                ? ContentResource::collection($this->getRelation('profileContents'))
                : [],
        ];
    }
}
