<?php

namespace App\Domain\Vtual\Http\Resources;

use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Vtual\Models\ManagementInvite */
class ManagementInviteResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * Token is never exposed in listings — it only travels via email/notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role instanceof \BackedEnum ? $this->role->value : $this->role,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'invited_by' => $this->whenLoaded('inviter', fn () => [
                'id' => $this->inviter->id,
                'name' => $this->inviter->name,
            ]),
            'management' => $this->whenLoaded('management', fn () => [
                'id' => $this->management->id,
                'name' => $this->management->name,
                'slug' => $this->management->slug,
            ]),
            ...$this->dateFields('expires_at', $this->expires_at),
            ...$this->dateFields('responded_at', $this->responded_at),
            ...$this->dateFields('created_at', $this->created_at),
        ];
    }
}
