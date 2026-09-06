<?php

namespace App\Domain\Vtual\Http\Resources;

use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Vtual\Models\CreatorManagementInvite */
class ManagementCreatorInviteResource extends JsonResource
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
            'mode' => $this->mode,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'new_display_name' => $this->new_display_name,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->display_name,
                'slug' => $this->creator->slug,
            ]),
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
