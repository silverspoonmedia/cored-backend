<?php

namespace App\Domain\Vtual\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Vtual\Models\ChannelClaim */
class ChannelClaimResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'creator_id' => $this->creator_id,
            'platform_id' => $this->platform_id,
            'external_id' => $this->external_id,
            'profile_input' => $this->profile_input,
            'handle' => $this->handle,
            'display_name' => $this->display_name,
            'url' => $this->url,
            'token' => $this->token,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'closed_reason' => $this->closed_reason instanceof \BackedEnum
                ? $this->closed_reason->value
                : $this->closed_reason,
            'attempt_count' => $this->attempt_count,
            'last_attempt_at' => $this->last_attempt_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verified_via' => $this->verified_via instanceof \BackedEnum
                ? $this->verified_via->value
                : $this->verified_via,
            'verified_by_user_id' => $this->verified_by_user_id,
            'channel_id' => $this->channel_id,
            'platform' => $this->whenLoaded('platform', fn () => new PlatformResource($this->platform)),
            'creator' => $this->whenLoaded('creator', fn () => new CreatorResource($this->creator)),
            'channel' => $this->whenLoaded('channel', fn () => new ChannelResource($this->channel)),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
