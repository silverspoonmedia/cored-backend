<?php

namespace App\Domain\Archivd\Http\Resources;

use App\Domain\Archivd\Models\WorkspaceInvite;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkspaceInvite */
class WorkspaceInviteResource extends JsonResource
{
    use FormatsPresentation;

    public function __construct($resource, private readonly bool $includeToken = false)
    {
        parent::__construct($resource);
    }

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
            'token' => $this->when($this->includeToken, $this->token),
            ...$this->dateFields('expires_at', $this->expires_at),
            ...$this->dateFields('accepted_at', $this->accepted_at),
            ...$this->dateFields('created_at', $this->created_at),
        ];
    }
}
