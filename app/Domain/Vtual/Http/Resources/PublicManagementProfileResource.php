<?php

namespace App\Domain\Vtual\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Guest-safe public management (talent org) profile.
 * No UUID, no members, no my_role — slug-addressed only.
 *
 * @mixin \App\Domain\Vtual\Models\Management
 */
class PublicManagementProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'logo_url' => $this->logo_url,
            'links' => $this->whenLoaded(
                'externalLinks',
                fn () => $this->externalLinks->map(fn ($link): array => [
                    'kind' => $link->kind instanceof \BackedEnum ? $link->kind->value : $link->kind,
                    'label' => $link->label,
                    'url' => $link->url,
                ])->values()->all(),
            ),
            'creators' => $this->whenLoaded(
                'creatorAssignments',
                fn () => PublicVtuberResource::collection(
                    $this->creatorAssignments
                        ->map(fn ($assignment) => $assignment->creator)
                        ->filter()
                        ->values(),
                ),
            ),
        ];
    }
}
