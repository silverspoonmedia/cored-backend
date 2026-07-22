<?php

namespace App\Domain\Vtual\Http\Resources;

use App\Domain\Vtual\Services\CreatorAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Vtual\Models\Creator */
class CreatorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();
        $myRole = null;

        if ($actor !== null) {
            $myRole = app(CreatorAccessService::class)->roleFor($this->resource, $actor)?->value;
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'display_name' => $this->display_name,
            'bio' => $this->bio,
            'avatar_url' => $this->avatar_url,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'my_role' => $myRole,
            'channels_count' => $this->when(
                isset($this->channels_count),
                fn () => (int) $this->channels_count,
            ),
            'members' => $this->whenLoaded('members', fn () => CreatorMemberResource::collection($this->members)),
            'channels' => $this->whenLoaded('channels', fn () => ChannelResource::collection($this->channels)),
            'external_links' => $this->whenLoaded(
                'externalLinks',
                fn () => ExternalLinkResource::collection($this->externalLinks),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
