<?php

namespace App\Domain\Microstock\Http\Controllers\API\V1;

use App\Domain\Archivd\Models\Folder;
use App\Domain\Microstock\Http\Requests\LockCollectionRequest;
use App\Domain\Microstock\Http\Requests\UploadCollectionRequest;
use App\Domain\Microstock\Http\Resources\MarketplaceUploadResource;
use App\Domain\Microstock\Services\CollectionService;
use App\Domain\Microstock\Services\MarketplaceUploadService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CollectionController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly CollectionService $collections,
        private readonly MarketplaceUploadService $uploads,
    ) {}

    public function lock(LockCollectionRequest $request, string $folder): JsonResponse
    {
        $data = $request->validated();

        $collection = $this->collections->lock(
            $request->user(),
            $folder,
            $data['hint'] ?? null,
            $data['billing_mode'] ?? null,
        );

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
     * Queue a marketplace upload for this collection (202). Additive to the CSV
     * path — the decision record keeps export as the primary route out.
     */
    public function upload(UploadCollectionRequest $request, string $folder): JsonResponse
    {
        $data = $request->validated();

        $upload = $this->uploads->queue(
            $request->user(),
            $folder,
            $data['credential_id'],
        );

        return $this->successResponse(
            $this->resolveResource(new MarketplaceUploadResource($upload)),
            __('api/microstock.upload_queued'),
            202,
        );
    }

    /**
     * Latest upload attempt for this collection (null when never attempted).
     */
    public function uploadStatus(Request $request, string $folder): JsonResponse
    {
        $upload = $this->uploads->latestFor($request->user(), $folder);

        return $this->successResponse(
            $upload !== null
                ? $this->resolveResource(new MarketplaceUploadResource($upload))
                : null,
            __('api/microstock.upload_status_loaded'),
        );
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
            'collection_billing_mode' => $collection->collection_billing_mode,
            'collection_locked' => $collection->isCollectionLocked(),
        ];
    }
}
