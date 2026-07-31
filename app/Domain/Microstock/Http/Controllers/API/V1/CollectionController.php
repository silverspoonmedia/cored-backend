<?php

namespace App\Domain\Microstock\Http\Controllers\API\V1;

use App\Domain\FileHosting\Models\Folder;
use App\Domain\Microstock\Services\CollectionService;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CollectionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CollectionService $collections,
    ) {}

    public function lock(Request $request, string $folder): JsonResponse
    {
        $data = $request->validate([
            'hint' => ['nullable', 'string', 'max:2000'],
        ]);

        $collection = $this->collections->lock($request->user(), $folder, $data['hint'] ?? null);

        return $this->successResponse(
            $this->payload($collection),
            __('api/microstock.collection_locked'),
        );
    }

    public function analyze(Request $request, string $folder): JsonResponse
    {
        $collection = $this->collections->analyze($request->user(), $folder);

        return $this->successResponse(
            $this->payload($collection),
            __('api/microstock.collection_processing'),
            202,
        );
    }

    public function status(Request $request, string $folder): JsonResponse
    {
        return $this->successResponse(
            $this->collections->status($request->user(), $folder),
            __('api/microstock.collection_status'),
        );
    }

    public function export(Request $request, string $folder): JsonResponse
    {
        $result = $this->collections->export($request->user(), $folder);

        return $this->successResponse(
            $result,
            __('api/microstock.collection_exported'),
        );
    }

    public function download(Request $request, string $folder): Response
    {
        $csv = $this->collections->downloadCsv($request->user(), $folder);

        return response($csv['contents'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$csv['name'].'"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Folder $collection): array
    {
        return [
            'id' => $collection->id,
            'collection_status' => $collection->collection_status?->value,
            'collection_hint' => $collection->collection_hint,
            'collection_locked' => $collection->isCollectionLocked(),
        ];
    }
}
