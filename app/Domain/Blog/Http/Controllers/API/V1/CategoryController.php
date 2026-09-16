<?php

namespace App\Domain\Blog\Http\Controllers\API\V1;

use App\Domain\Blog\Http\Requests\StoreCategoryRequest;
use App\Domain\Blog\Http\Requests\UpdateCategoryRequest;
use App\Domain\Blog\Http\Resources\CategoryResource;
use App\Domain\Blog\Models\Category;
use App\Domain\Blog\Services\CategoryService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly CategoryService $categories,
    ) {}

    public function index(): JsonResponse
    {
        $rows = collect($this->categories->list())
            ->map(fn (Category $category) => $this->resolveResource(new CategoryResource($category)))
            ->values()
            ->all();

        return $this->successResponse($rows, __('api/blog.categories.list_success'));
    }

    public function show(Category $category): JsonResponse
    {
        return $this->successResponse(
            $this->resolveResource(new CategoryResource($this->categories->read($category->id))),
            __('api/blog.categories.show_success'),
        );
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categories->create($request->validated());

        return $this->successResponse(
            $this->resolveResource(new CategoryResource($category)),
            __('api/blog.categories.created'),
            201,
        );
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $updated = $this->categories->update($category, $request->validated());

        return $this->successResponse(
            $this->resolveResource(new CategoryResource($updated)),
            __('api/blog.categories.updated'),
        );
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->categories->delete($category);

        return $this->successResponse(null, __('api/blog.categories.deleted'));
    }
}
