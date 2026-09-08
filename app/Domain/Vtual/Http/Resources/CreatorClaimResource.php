<?php

namespace App\Domain\Vtual\Http\Resources;

use App\Domain\Vtual\Services\CreatorClaimService;
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
        $actor = $request->user();
        $claims = app(CreatorClaimService::class);
        $maxAttempts = $claims->maxAttempts();
        $attemptCount = (int) $this->attempt_count;
        $blockedUntil = $actor !== null
            ? $claims->blockedUntil($actor, (string) $this->creator_id)
            : null;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'creator_id' => $this->creator_id,
            'channel_id' => $this->channel_id,
            'token' => $this->when(
                $actor !== null && (string) $actor->getAuthIdentifier() === (string) $this->user_id,
                $this->token,
            ),
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'closed_reason' => $this->closed_reason instanceof \BackedEnum
                ? $this->closed_reason->value
                : $this->closed_reason,
            'attempt_count' => $attemptCount,
            'attempts_remaining' => max(0, $maxAttempts - $attemptCount),
            'max_attempts' => $maxAttempts,
            'verify_queue_status' => $claims->verifyQueueStatus($this->resource),
            'can_verify' => $actor !== null && $claims->canQueueVerify($this->resource, $actor),
            ...$this->dateFields('blocked_until', $blockedUntil),
            ...$this->dateFields('last_attempt_at', $this->last_attempt_at),
            ...$this->dateFields('expires_at', $this->expires_at),
            ...$this->dateFields('verified_at', $this->verified_at),
            'verified_via' => $this->verified_via instanceof \BackedEnum
                ? $this->verified_via->value
                : $this->verified_via,
            'verified_by_user_id' => $this->verified_by_user_id,
            'verified_channel_id' => $this->verified_channel_id,
            'creator' => $this->whenLoaded('creator', fn () => new CreatorResource($this->creator)),
            'channel' => $this->whenLoaded('channel', fn () => new ChannelResource($this->channel)),
            'verified_channel' => $this->whenLoaded('verifiedChannel', fn () => $this->verifiedChannel !== null ? new ChannelResource($this->verifiedChannel) : null),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->when(
                    $actor !== null && (
                        (string) $actor->getAuthIdentifier() === (string) $this->user_id
                        || $actor->can('view any vtual resource')
                        || $actor->can('manage any vtual resource')
                    ),
                    $this->user?->email,
                ),
            ]),
            ...$this->dateFields('created_at', $this->created_at),
            ...$this->dateFields('updated_at', $this->updated_at),
        ];
    }
}
