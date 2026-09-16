<?php

namespace App\Domain\Blog\Http\Requests;

use App\Domain\Blog\Enums\PostStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StorePostRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('blog_posts', 'slug')->whereNull('deleted_at')],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'content' => ['sometimes', 'nullable', 'string', 'max:1000000'],
            'status' => ['sometimes', new Enum(PostStatus::class)],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'featured_media_id' => [
                'sometimes',
                'nullable',
                Rule::exists('blog_media', 'id'),
            ],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['string', Rule::exists('blog_categories', 'id')],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['string', Rule::exists('blog_tags', 'id')],
        ];
    }
}
