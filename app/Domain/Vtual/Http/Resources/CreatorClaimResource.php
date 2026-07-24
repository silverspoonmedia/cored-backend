<?php

namespace App\Domain\Vtual\Http\Resources;

use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Vtual\Models\CreatorClaim */
class CreatorClaimResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'creator_id' => $this->creator_id,
            'channel_id' => $this->channel_id,
            'token' => $this->token,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'closed_reason' => $this->closed_reason instanceof \BackedEnum
                ? $this->closed_reason->value
                : $this->closed_reason,
            'attempt_count' => $this->attempt_count,
            ...$this->dateFields('last_attempt_at', $this->last_attempt_at),
            ...$this->dateFields('expires_at', $this->expires_at),
            ...$this->dateFields('verified_at', $this->verified_at),
            'verified_via' => $this->verified_via instanceof \BackedEnum
                ? $this->verified_via->value
                : $this->verified_via,
            'verified_by_user_id' => $this->verified_by_user_id,
            'creator' => $this->whenLoaded('creator', fn () => new CreatorResource($this->creator)),
            'channel' => $this->whenLoaded('channel', fn () => new ChannelResource($this->channel)),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            ...$this->dateFields('created_at', $this->created_at),
            ...$this->dateFields('updated_at', $this->updated_at),
        ];
    }
}
