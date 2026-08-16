<?php

namespace App\Domain\Vtual\Http\Resources;

use App\Domain\Vtual\Services\CreatorAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Vtual\Models\CreatorMember */
class CreatorMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'creator_id' => $this->creator_id,
            'user_id' => $this->user_id,
            'role' => $this->role instanceof \BackedEnum ? $this->role->value : $this->role,
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

        if ($actor->can('view any vtual resource') || $actor->can('manage any vtual resource')) {
            return true;
        }

        $this->loadMissing('creator');

        return $this->creator !== null
            && app(CreatorAccessService::class)->canManageMembers($this->creator, $actor);
    }
}
