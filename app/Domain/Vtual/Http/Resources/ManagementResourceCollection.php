<?php

namespace App\Domain\Vtual\Http\Resources;

use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Services\CreatorAccessService;
use App\Domain\Vtual\Services\ManagementAccessService;
use App\Domain\Vtual\Services\ManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ManagementResourceCollection extends ResourceCollection
{
    public $collects = ManagementResource::class;

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        $managements = $this->collection->map(
            fn (mixed $item): mixed => $item instanceof ManagementResource ? $item->resource : $item,
        )->filter(fn (mixed $item): bool => $item instanceof Management);

        app(ManagementService::class)->primeClaimableLookup($request, $managements);

        $actor = $request->user();
        if ($actor !== null) {
            app(ManagementAccessService::class)->primeRequestLookup($request, $managements, $actor);

            $creators = $managements->flatMap(function (Management $management) {
                if (! $management->relationLoaded('creatorAssignments')) {
                    return [];
                }

                return $management->creatorAssignments
                    ->filter(fn ($a) => $a->left_at === null && $a->relationLoaded('creator') && $a->creator instanceof Creator)
                    ->map(fn ($a) => $a->creator);
            });

            app(CreatorAccessService::class)->primeRequestLookup($request, $creators, $actor);
        }

        return parent::toArray($request);
    }
}
