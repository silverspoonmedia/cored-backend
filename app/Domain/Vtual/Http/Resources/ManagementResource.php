<?php

namespace App\Domain\Vtual\Http\Resources;

use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Services\ManagementAccessService;
use App\Domain\Vtual\Services\ManagementService;
use App\Http\Resources\Concerns\FormatsPresentation;
use App\Support\Presentation\DatePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Management */
class ManagementResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * @param  mixed  $resource
     */
    protected static function newCollection($resource): ManagementResourceCollection
    {
        return new ManagementResourceCollection($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();
        $myRole = null;

        if ($actor !== null) {
            $myRole = app(ManagementAccessService::class)->roleForRequest($this->resource, $actor, $request)?->value;
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'logo_url' => $this->logo_url,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'my_role' => $myRole,
            'is_claimable' => app(ManagementService::class)->isClaimableForRequest($this->resource, $request),
            'members' => $this->whenLoaded('members', fn () => ManagementMemberResource::collection($this->members)),
            'creators' => $this->whenLoaded('creatorAssignments', function () use ($request) {
                return $this->creatorAssignments
                    ->filter(fn ($a) => $a->left_at === null && $a->relationLoaded('creator'))
                    ->map(function ($a) use ($request) {
                        [$iso, $formatted, $human] = DatePresenter::pack($a->joined_at);

                        return [
                            'assignment_id' => $a->id,
                            'joined_at' => $iso,
                            'joined_at_formatted' => $formatted,
                            'joined_at_human' => $human,
                            'creator' => (new CreatorResource($a->creator))->resolve($request),
                        ];
                    })
                    ->values()
                    ->all();
            }),
            // Delegated per-creator grants (additive on top of the role). Only
            // meaningful with the linked-creators table, so it ships alongside.
            'creator_grants' => $this->whenLoaded('creatorGrants', function () {
                return $this->creatorGrants
                    ->map(fn ($grant) => [
                        'id' => $grant->id,
                        'member_id' => $grant->member_id,
                        'creator_id' => $grant->creator_id,
                        'capability' => $grant->capability instanceof \BackedEnum
                            ? $grant->capability->value
                            : $grant->capability,
                        'creator_display_name' => $grant->relationLoaded('creator')
                            ? $grant->creator?->display_name
                            : null,
                        'member_user_name' => $grant->relationLoaded('member')
                            ? $grant->member?->user?->name
                            : null,
                    ])
                    ->values()
                    ->all();
            }),
            ...$this->dateFields('created_at', $this->created_at),
            ...$this->dateFields('updated_at', $this->updated_at),
        ];
    }
}
