<?php

namespace App\Domain\Vtual\Http\Resources;

use App\Domain\Vtual\Services\ManagementAccessService;
use App\Http\Resources\Concerns\FormatsPresentation;
use App\Support\Presentation\DatePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Vtual\Models\Management */
class ManagementResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();
        $myRole = null;

        if ($actor !== null) {
            $myRole = app(ManagementAccessService::class)->roleFor($this->resource, $actor)?->value;
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'logo_url' => $this->logo_url,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'my_role' => $myRole,
            'members' => $this->whenLoaded('members', fn () => ManagementMemberResource::collection($this->members)),
            'creators' => $this->whenLoaded('creatorAssignments', function () {
                return $this->creatorAssignments
                    ->filter(fn ($a) => $a->left_at === null && $a->relationLoaded('creator'))
                    ->map(function ($a) {
                        [$iso, $formatted, $human] = DatePresenter::pack($a->joined_at);

                        return [
                            'assignment_id' => $a->id,
                            'joined_at' => $iso,
                            'joined_at_formatted' => $formatted,
                            'joined_at_human' => $human,
                            'creator' => (new CreatorResource($a->creator))->resolve(),
                        ];
                    })
                    ->values()
                    ->all();
            }),
            ...$this->dateFields('created_at', $this->created_at),
            ...$this->dateFields('updated_at', $this->updated_at),
        ];
    }
}
