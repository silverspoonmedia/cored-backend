<?php

namespace App\Domain\Blog\Http\Controllers\API\V1;

use App\Domain\Blog\Enums\PostStatus;
use App\Domain\Blog\Http\Requests\IndexPostRequest;
use App\Domain\Blog\Http\Requests\StorePostRequest;
use App\Domain\Blog\Http\Requests\UpdatePostRequest;
use App\Domain\Blog\Http\Resources\PostDetailResource;
use App\Domain\Blog\Http\Resources\PostListResource;
use App\Domain\Blog\Models\Post;
use App\Domain\Blog\Services\PostService;
use App\Http\Controllers\Concerns\RespondsWithListDisplayMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\ListActions\Builders\PostQueryRowActions;
use App\Support\ListActions\Builders\PostTrashedQueryRowActions;
use App\Support\ListActions\QueryRowActionAttacher;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PostController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;
    use RespondsWithListDisplayMode;

    public function __construct(
        private readonly PostService $posts,
        private readonly PostQueryRowActions $postQueryRowActions,
        private readonly PostTrashedQueryRowActions $postTrashedQueryRowActions,
    ) {}

    public function index(IndexPostRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $list = Post::query()
            ->with(['author:id,name', 'categories:id,name,slug', 'tags:id,name,slug', 'featuredMedia'])
            ->byStatus($validated['status'] ?? null)
            ->search($validated['q'] ?? null)
            ->latest('updated_at')
            ->get();

        return $this->respondToQueryList(
            $request,
            $list,
            fn ($list) => QueryRowActionAttacher::attach(
                $this->resolveResource(PostListResource::collection($list)),
                $list,
                $this->postQueryRowActions,
                $request->user('api'),
            ),
            __('api/blog.list_success'),
        );
    }

    public function show(Post $post): JsonResponse
    {
        return $this->successResponse(
            $this->resolveResource(new PostDetailResource($this->posts->present($post))),
            __('api/blog.show_success'),
        );
    }

    public function trashed(IndexPostRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $list = Post::onlyTrashed()
            ->with(['author:id,name', 'categories:id,name,slug', 'tags:id,name,slug', 'featuredMedia'])
            ->search($validated['q'] ?? null)
            ->latest('deleted_at')
            ->get();

        return $this->respondToQueryList(
            $request,
            $list,
            fn ($list) => QueryRowActionAttacher::attach(
                $this->resolveResource(PostListResource::collection($list)),
                $list,
                $this->postTrashedQueryRowActions,
                $request->user('api'),
            ),
            __('api/blog.trashed_success'),
        );
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        /** @var \App\Domain\Identity\Models\User $user */
        $user = $request->user('api');

        $post = $this->posts->create($request->validated(), $user);

        return $this->successResponse(
            $this->resolveResource(new PostDetailResource($post)),
            __('api/blog.created'),
            201,
        );
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $updated = $this->posts->update($post, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new PostDetailResource($updated)),
            __('api/blog.updated'),
        );
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->posts->delete($post);

        return $this->successResponse(null, __('api/blog.deleted'));
    }

    public function restore(Post $post): JsonResponse
    {
        $restored = $this->posts->restore($post);

        return $this->successResponse(
            $this->resolveResource(new PostDetailResource($restored)),
            __('api/blog.restored'),
        );
    }

    public function statuses(): JsonResponse
    {
        return $this->successResponse(
            ['statuses' => PostStatus::values()],
            __('api/blog.statuses_success'),
        );
    }
}
