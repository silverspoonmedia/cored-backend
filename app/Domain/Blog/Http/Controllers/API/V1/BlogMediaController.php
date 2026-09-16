<?php

namespace App\Domain\Blog\Http\Controllers\API\V1;

use App\Domain\Blog\Http\Resources\BlogMediaResource;
use App\Domain\Blog\Models\BlogMedia;
use App\Domain\Blog\Services\BlogMediaService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogMediaController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly BlogMediaService $media,
    ) {}

    public function store(StoreBlogMediaRequest $request): JsonResponse
    {
        /** @var \App\Domain\Identity\Models\User $user */
        $user = $request->user('api');

        $media = $this->media->store($request->file('file'), $user->id);

        return $this->successResponse(
            $this->resolveResource(new BlogMediaResource($media)),
            __('api/blog.media.uploaded'),
            201,
        );
    }

    public function destroy(BlogMedia $media): JsonResponse
    {
        $this->media->delete($media);

        return $this->successResponse(null, __('api/blog.media.deleted'));
    }
}

class StoreBlogMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,gif,webp',
                'max:' . max(1, (int) config('blog.media.max_size_kb', 4096)),
            ],
        ];
    }
}
