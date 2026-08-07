<?php

namespace App\Domain\Vtual\Http\Resources;

use App\Domain\Vtual\Services\CreatorClaimService;
use App\Domain\Vtual\Services\CreatorService;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Vtual\Models\Creator */
class VtuberProfileResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isClaimable = app(CreatorService::class)->isClaimable($this->resource);
        $actor = $request->user();
        $blockedUntil = $actor !== null
            ? app(CreatorClaimService::class)->blockedUntil($actor, (string) $this->id)
            : null;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'profile_url' => route('vtual.vtuber.show', $this->slug),
            'is_claimable' => $isClaimable,
            'claim_process_url' => $isClaimable
                ? route('vtual.vtuber.claim', $this->slug)
                : null,
            ...$this->dateFields('claim_blocked_until', $blockedUntil),
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
