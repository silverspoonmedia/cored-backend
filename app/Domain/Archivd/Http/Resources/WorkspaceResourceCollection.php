<?php

namespace App\Domain\Archivd\Http\Resources;

use App\Domain\Archivd\Models\Workspace;
use App\Domain\Archivd\Services\WorkspaceAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class WorkspaceResourceCollection extends ResourceCollection
{
    public $collects = WorkspaceResource::class;

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();
        if ($actor !== null) {
            app(WorkspaceAccessService::class)->primeRequestLookup(
                $request,
                $this->collection->map(fn (mixed $item): mixed => $item instanceof WorkspaceResource ? $item->resource : $item)
                    ->filter(fn (mixed $item): bool => $item instanceof Workspace),
                $actor,
            );
        }

        return parent::toArray($request);
    }
}
