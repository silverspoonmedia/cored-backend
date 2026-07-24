<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1;

use App\Domain\Vtual\Http\Requests\StoreExternalLinkRequest;
use App\Domain\Vtual\Http\Requests\UpdateExternalLinkRequest;
use App\Domain\Vtual\Http\Resources\ExternalLinkResource;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\ExternalLink;
use App\Domain\Vtual\Services\ExternalLinkService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalLinkController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly ExternalLinkService $links,
    ) {}

    public function index(Request $request, Creator $creator): JsonResponse
    {
        $this->authorize('view', $creator);

        return $this->successResponse(
            $this->resolveResource(ExternalLinkResource::collection(
                $this->links->listForCreator($request->user(), $creator->id)
            )),
            __('api/vtual.link.list_success'),
        );
    }

    public function store(StoreExternalLinkRequest $request, Creator $creator): JsonResponse
    {
        $this->authorize('update', $creator);

        $link = $this->links->create($request->user(), $creator->id, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new ExternalLinkResource($link)),
            __('api/vtual.link.created'),
            201,
        );
    }

    public function update(UpdateExternalLinkRequest $request, ExternalLink $link): JsonResponse
    {
        $updated = $this->links->update($request->user(), $link->id, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new ExternalLinkResource($updated)),
            __('api/vtual.link.updated'),
        );
    }

    public function destroy(Request $request, ExternalLink $link): JsonResponse
    {
        $this->links->delete($request->user(), $link->id);

        return $this->successResponse(null, __('api/vtual.link.deleted'));
    }
}
