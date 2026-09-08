<?php

namespace App\Domain\Microstock\Http\Controllers\API\V1;

use App\Domain\Microstock\Http\Requests\UpdateAssetMetadataRequest;
use App\Domain\Microstock\Http\Resources\AssetMetadataResource;
use App\Domain\Microstock\Services\MicrostockService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetadataController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly MicrostockService $microstock,
    ) {}

    public function analyze(Request $request, string $file): JsonResponse
    {
        $metadata = $this->microstock->requestAnalysis($request->user(), $file);

        return $this->successResponse(
            $this->resolveResource(new AssetMetadataResource($metadata)),
            __('api/microstock.analysis_queued'),
            202,
        );
    }

    public function show(Request $request, string $file): JsonResponse
    {
        $metadata = $this->microstock->metadataFor($request->user(), $file);

        return $this->successResponse(
            $this->resolveResource(new AssetMetadataResource($metadata)),
            __('api/microstock.metadata_loaded'),
        );
    }

    public function update(UpdateAssetMetadataRequest $request, string $file): JsonResponse
    {
        $data = $request->validated();

        $metadata = $this->microstock->finalize(
            $request->user(),
            $file,
            $data['title'],
            $data['keywords'],
        );

        return $this->successResponse(
            $this->resolveResource(new AssetMetadataResource($metadata)),
            __('api/microstock.metadata_saved'),
        );
    }
}
