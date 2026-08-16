<?php

namespace App\Domain\FileHosting\Http\Resources;

use App\Domain\FileHosting\Models\WorkspaceMember;
use App\Domain\FileHosting\Services\WorkspaceAccessService;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkspaceMember */
class WorkspaceMemberResource extends JsonResource
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
            'user_id' => $this->user_id,
            'role' => $this->role instanceof \BackedEnum ? $this->role->value : $this->role,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            ...$this->dateFields('joined_at', $this->joined_at),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->when($this->viewerMaySeeEmail($request), $this->user->email),
            ]),
        ];
    }

    private function viewerMaySeeEmail(Request $request): bool
    {
        $actor = $request->user();

        if ($actor === null) {
            return false;
        }

        if ((string) $actor->getAuthIdentifier() === (string) $this->user_id) {
            return true;
        }

        if ($actor->can('view any file hosting resource') || $actor->can('manage any file hosting resource')) {
            return true;
        }

        $this->loadMissing('workspace');

        return $this->workspace !== null
            && app(WorkspaceAccessService::class)->canManageMembers($this->workspace, $actor);
    }
}
