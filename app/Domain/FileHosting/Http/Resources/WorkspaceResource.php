<?php

namespace App\Domain\FileHosting\Http\Resources;

use App\Domain\FileHosting\Services\WorkspaceAccessService;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\FileHosting\Models\Workspace */
class WorkspaceResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();
        $myRole = null;
        $isOwner = false;

        if ($actor !== null) {
            $isOwner = $this->owner_user_id === $actor->id;
            $role = app(WorkspaceAccessService::class)->roleFor($this->resource, $actor);
            $myRole = $role?->value;
        }

        return [
            'id' => $this->id,
            'owner_user_id' => $this->owner_user_id,
            'name' => $this->name,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'is_owner' => $isOwner,
            'my_role' => $myRole,
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ]),
            'members' => $this->whenLoaded('activeMembers', fn () => WorkspaceMemberResource::collection($this->activeMembers)),
            ...$this->dateFields('created_at', $this->created_at),
            ...$this->dateFields('updated_at', $this->updated_at),
        ];
    }
}
