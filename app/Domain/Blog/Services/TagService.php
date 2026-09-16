<?php

namespace App\Domain\Blog\Services;

use App\Domain\Blog\Models\Tag;
use Illuminate\Support\Str;

class TagService
{
    /**
     * @return list<Tag>
     */
    public function list(): array
    {
        return Tag::query()
            ->withCount('posts')
            ->orderBy('name')
            ->get()
            ->all();
    }

    public function read(string $id): Tag
    {
        return Tag::query()->withCount('posts')->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Tag
    {
        return Tag::query()->create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['slug'] ?? null, $data['name']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Tag $tag, array $data): Tag
    {
        $payload = [];

        if (array_key_exists('name', $data)) {
            $payload['name'] = $data['name'];
        }

        $slugInput = $data['slug'] ?? null;
        $slugBase = $slugInput !== null && $slugInput !== ''
            ? $slugInput
            : ($data['name'] ?? $tag->name);

        if (array_key_exists('slug', $data) || array_key_exists('name', $data)) {
            $payload['slug'] = $this->uniqueSlug($slugBase, $data['name'] ?? $tag->name, $tag->id);
        }

        $tag->fill($payload)->save();

        return $tag->refresh();
    }

    public function delete(Tag $tag): void
    {
        $tag->delete();
    }

    private function uniqueSlug(?string $slug, string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($slug !== null && $slug !== '' ? $slug : $name);
        $base = $base !== '' ? $base : 'tag-'.Str::lower(Str::random(6));

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
        $query = Tag::query()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
