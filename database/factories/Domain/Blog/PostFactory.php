<?php

namespace Database\Factories\Domain\Blog;

use App\Domain\Blog\Enums\PostStatus;
use App\Domain\Blog\Models\Post;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /** @use HasFactory<Post> */
    use HasFactory;

    protected $model = Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'title' => $title,
            'slug' => str()->slug($title).'-'.str()->lower(str()->random(6)),
            'excerpt' => fake()->sentence(10),
            'content' => '<p>'.implode('</p><p>', fake()->paragraphs(3)).'</p>',
            'status' => PostStatus::Draft->value,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Published->value,
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Draft->value,
            'published_at' => null,
        ]);
    }
}
