<?php

namespace App\Domain\Archivd\Http\Controllers\API\V1;

use App\Domain\Archivd\Http\Requests\StoreHashBlocklistRequest;
use App\Domain\Archivd\Http\Resources\HashBlocklistResource;
use App\Domain\Archivd\Models\ArchivdHashBlocklist;
use App\Domain\Archivd\Services\HashBlocklistService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListActions\Builders\HashBlocklistQueryRowActions;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HashBlocklistController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly HashBlocklistService $blocklist,
        private readonly HashBlocklistQueryRowActions $queryRowActions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $entries = ArchivdHashBlocklist::query()
            ->with('creator')
            ->latest('created_at')
            ->get();

        return $this->successResponse(
            QueryRowActionAttacher::attach(
                $this->resolveResource(HashBlocklistResource::collection($entries)),
                $entries,
                $this->queryRowActions,
                $request->user(),
            ),
            __('api/archivd.blocklist_loaded'),
        );
    }

    public function store(StoreHashBlocklistRequest $request): JsonResponse
    {
        $entry = $this->blocklist->block(
            $request->validated('sha256'),
            $request->validated('reason'),
            $request->user()?->id,
        );

        $entry->load('creator');

        return $this->successResponse(
            $this->resolveResource(new HashBlocklistResource($entry)),
            __('api/archivd.blocklist_saved'),
            201,
        );
    }

    public function destroy(string $entry): JsonResponse
    {
        $record = ArchivdHashBlocklist::query()->find($entry);

        if ($record !== null) {
            $this->blocklist->unblock($record->sha256);
        }

        return $this->successResponse(null, __('api/archivd.blocklist_removed'));
    }
}
