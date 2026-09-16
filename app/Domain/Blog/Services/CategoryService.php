<?php

namespace App\Domain\Blog\Services;

use App\Domain\Blog\Models\Category;
use Illuminate\Support\Str;

class CategoryService
{
    /**
     * @return list<Category>
     */
    public function list(): array
    {
        return Category::query()
            ->withCount('posts')
            ->orderBy('name')
            ->get()
            ->all();
    }

    public function read(string $id): Category
    {
        return Category::query()->withCount('posts')->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Category
    {
        return Category::query()->create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['slug'] ?? null, $data['name']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Category $category, array $data): Category
    {
        $payload = [];

        if (array_key_exists('name', $data)) {
            $payload['name'] = $data['name'];
        }

        $slugInput = $data['slug'] ?? null;
        $slugBase = $slugInput !== null && $slugInput !== ''
            ? $slugInput
            : ($data['name'] ?? $category->name);

        if (array_key_exists('slug', $data) || array_key_exists('name', $data)) {
            $payload['slug'] = $this->uniqueSlug($slugBase, $data['name'] ?? $category->name, $category->id);
        }

        $category->fill($payload)->save();

        return $category->refresh();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }

    private function uniqueSlug(?string $slug, string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($slug !== null && $slug !== '' ? $slug : $name);
        $base = $base !== '' ? $base : 'category-'.Str::lower(Str::random(6));

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
        $query = Category::query()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
