<?php

namespace App\Domain\Vtual\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

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
                fn () => PublicVtuberResource::collection($this->rosterCreators()),
            ),
            'stats' => $this->whenLoaded('creatorAssignments', fn (): array => $this->rosterStats()),
        ];
    }

    /**
     * Roster is already filtered by the service (publicly listed creators
     * only); the helper keeps `creators` and `stats` reading one collection.
     *
     * @return Collection<int, \App\Domain\Vtual\Models\Creator>
     */
    private function rosterCreators(): Collection
    {
        return $this->creatorAssignments
            ->map(fn ($assignment) => $assignment->creator)
            ->filter()
            ->values();
    }

    /**
     * @return array<string, int|string>
     */
    private function rosterStats(): array
    {
        $creators = $this->rosterCreators();

        $subscribers = (int) $creators->sum(
            fn ($creator): int => (int) $creator->channels->sum(
                fn ($channel): int => (int) ($channel->subscriber_count ?? 0),
            ),
        );

        return [
            'creators' => $creators->count(),
            // withExists yields int 1/0, so cast instead of a strict compare.
            'live' => $creators->filter(fn ($creator): bool => (bool) $creator->is_live)->count(),
            'channels' => (int) $creators->sum(fn ($creator): int => $creator->channels->count()),
            'subscribers_total' => $subscribers,
            'subscribers_total_formatted' => Number::format($subscribers),
        ];
    }
}
