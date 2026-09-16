<?php

namespace App\Domain\Blog\Services;

use App\Domain\Blog\Enums\PostStatus;
use App\Domain\Blog\Models\Post;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

class PostService
{
    /**
     * HTML tags the Tiptap editor produces; server-side whitelist is the
     * single source of truth because content is rendered via v-html.
     */
    private const PURIFIER_PROFILE = 'blog';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $author): Post
    {
        $post = DB::transaction(function () use ($data, $author): Post {
            $post = Post::query()->create([
                'author_id' => $author->id,
                'title' => $data['title'],
                'slug' => $this->uniqueSlug($data['slug'] ?? null, $data['title']),
                'excerpt' => $data['excerpt'] ?? null,
                'content' => $this->sanitize($data['content'] ?? null),
                'status' => $data['status'] ?? PostStatus::Draft->value,
                'published_at' => $data['published_at'] ?? null,
                'featured_media_id' => $data['featured_media_id'] ?? null,
            ]);

            $post->categories()->sync($data['category_ids'] ?? []);
            $post->tags()->sync($data['tag_ids'] ?? []);

            return $post;
        });

        return $this->present($post);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Post $post, array $data): Post
    {
        $post = DB::transaction(function () use ($post, $data): Post {
            $payload = [];

            if (array_key_exists('title', $data)) {
                $payload['title'] = $data['title'];
            }

            $slugInput = $data['slug'] ?? null;
            $slugBase = $slugInput !== null && $slugInput !== ''
                ? $slugInput
                : ($data['title'] ?? $post->title);

            if (array_key_exists('slug', $data) || array_key_exists('title', $data)) {
                $payload['slug'] = $this->uniqueSlug($slugBase, $data['title'] ?? $post->title, $post->id);
            }

            foreach (['excerpt', 'published_at', 'featured_media_id'] as $field) {
                if (array_key_exists($field, $data)) {
                    $payload[$field] = $data[$field];
                }
            }

            if (array_key_exists('content', $data)) {
                $payload['content'] = $this->sanitize($data['content']);
            }

            if (array_key_exists('status', $data)) {
                $payload['status'] = $data['status'];

                if ($data['status'] === PostStatus::Published->value && $post->published_at === null && ! array_key_exists('published_at', $payload)) {
                    $payload['published_at'] = now();
                }
            }

            $post->fill($payload)->save();

            if (array_key_exists('category_ids', $data)) {
                $post->categories()->sync($data['category_ids'] ?? []);
            }

            if (array_key_exists('tag_ids', $data)) {
                $post->tags()->sync($data['tag_ids'] ?? []);
            }

            return $post->refresh();
        });

        return $this->present($post);
    }

    public function delete(Post $post): void
    {
        $post->delete();
    }

    public function restore(Post $post): Post
    {
        $post->restore();

        return $this->present($post);
    }

    /**
     * Detail model with all relations the detail resource expects.
     */
    public function present(Post $post): Post
    {
        return $post->load(['author:id,name', 'categories', 'tags', 'featuredMedia']);
    }

    /**
     * Strip everything outside the editor whitelist. Null-safe so a post may
     * hold empty content while drafting.
     */
    private function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        return Purifier::clean($html, self::PURIFIER_PROFILE);
    }

    /**
     * Ensure slug uniqueness against active (non-trashed) rows, appending -2,
     * -3, ... when needed.
     */
    private function uniqueSlug(?string $slug, string $title, ?string $ignoreId = null): string
    {
        $base = Str::slug($slug !== null && $slug !== '' ? $slug : $title);
        $base = $base !== '' ? $base : 'post-'.Str::lower(Str::random(6));

        $candidate = $base;
        $suffix = 2;

        while ($this->slugTaken($candidate, $ignoreId)) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function slugTaken(string $slug, ?string $ignoreId): bool
    {
        $query = Post::withTrashed()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
