<?php

namespace App\Domain\FileHosting\Http\Resources;

use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\FileHosting\Models\WorkspaceInvite */
class WorkspaceInviteResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'email' => $this->email,
            'role' => $this->role instanceof \BackedEnum ? $this->role->value : $this->role,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'token' => $this->token,
            ...$this->dateFields('expires_at', $this->expires_at),
            ...$this->dateFields('accepted_at', $this->accepted_at),
            ...$this->dateFields('created_at', $this->created_at),
        ];
    }
}
