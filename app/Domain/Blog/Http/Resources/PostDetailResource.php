<?php

namespace App\Domain\Blog\Http\Resources;

use App\Domain\Blog\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Post
 */
class PostDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'author_id' => $this->author_id,
            'author_name' => $this->whenLoaded('author', fn () => $this->author?->name),
            'category_ids' => $this->whenLoaded('categories', fn () => $this->categories->pluck('id')->values()->all()),
            'tag_ids' => $this->whenLoaded('tags', fn () => $this->tags->pluck('id')->values()->all()),
            'featured_media' => $this->whenLoaded('featuredMedia', fn () => $this->featuredMedia !== null
                ? new BlogMediaResource($this->featuredMedia)
                : null),
        ];
    }
}
