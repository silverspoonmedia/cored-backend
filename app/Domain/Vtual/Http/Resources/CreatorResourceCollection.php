<?php

namespace App\Domain\Vtual\Http\Resources;

use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Services\CreatorAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CreatorResourceCollection extends ResourceCollection
{
    public $collects = CreatorResource::class;

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();
        if ($actor !== null) {
            $creators = $this->collection->map(fn (mixed $item): mixed => $item instanceof CreatorResource ? $item->resource : $item)
                ->filter(fn (mixed $item): bool => $item instanceof Creator);

            app(CreatorAccessService::class)->primeRequestLookup($request, $creators, $actor);
            app(CreatorAccessService::class)->primeGrantLookup($request, $creators, $actor);
        }

        return parent::toArray($request);
    }
}
