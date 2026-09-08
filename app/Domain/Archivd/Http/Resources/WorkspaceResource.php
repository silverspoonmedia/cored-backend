<?php

namespace App\Domain\Archivd\Http\Resources;

use App\Domain\Archivd\Models\Workspace;
use App\Domain\Archivd\Services\WorkspaceAccessService;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Workspace */
class WorkspaceResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * @param  mixed  $resource
     */
    protected static function newCollection($resource): WorkspaceResourceCollection
    {
        return new WorkspaceResourceCollection($resource);
    }

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
            $role = app(WorkspaceAccessService::class)->roleForRequest($this->resource, $actor, $request);
            $myRole = $role?->value;
        }

        return [
            'id' => $this->id,
            'owner_user_id' => $this->owner_user_id,
            'name' => $this->name,
            'kind' => $this->kind instanceof \BackedEnum ? $this->kind->value : $this->kind,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'is_owner' => $isOwner,
            'my_role' => $myRole,
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->when($this->viewerMaySeeMemberEmail($request), $this->owner->email),
            ]),
            'members' => $this->whenLoaded('activeMembers', fn () => WorkspaceMemberResource::collection($this->activeMembers)),
            ...$this->dateFields('created_at', $this->created_at),
            ...$this->dateFields('updated_at', $this->updated_at),
        ];
    }

    private function viewerMaySeeMemberEmail(Request $request): bool
    {
        $actor = $request->user();

        if ($actor === null) {
            return false;
        }

        if ($actor->can('view any archivd resource') || $actor->can('manage any archivd resource')) {
            return true;
        }

        return app(WorkspaceAccessService::class)
            ->roleForRequest($this->resource, $actor, $request)
            ?->canManageMembers() ?? false;
    }
}
