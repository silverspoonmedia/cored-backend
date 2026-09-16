<?php

namespace App\Domain\Blog\Http\Controllers\API\V1;

use App\Domain\Blog\Http\Requests\StoreTagRequest;
use App\Domain\Blog\Http\Requests\UpdateTagRequest;
use App\Domain\Blog\Http\Resources\TagResource;
use App\Domain\Blog\Models\Tag;
use App\Domain\Blog\Services\TagService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class TagController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly TagService $tags,
    ) {}

    public function index(): JsonResponse
    {
        $rows = collect($this->tags->list())
            ->map(fn (Tag $tag) => $this->resolveResource(new TagResource($tag)))
            ->values()
            ->all();

        return $this->successResponse($rows, __('api/blog.tags.list_success'));
    }

    public function show(Tag $tag): JsonResponse
    {
        return $this->successResponse(
            $this->resolveResource(new TagResource($this->tags->read($tag->id))),
            __('api/blog.tags.show_success'),
        );
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = $this->tags->create($request->validated());

        return $this->successResponse(
            $this->resolveResource(new TagResource($tag)),
            __('api/blog.tags.created'),
            201,
        );
    }

    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse
    {
        $updated = $this->tags->update($tag, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new TagResource($updated)),
            __('api/blog.tags.updated'),
        );
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $this->tags->delete($tag);

        return $this->successResponse(null, __('api/blog.tags.deleted'));
    }
}
