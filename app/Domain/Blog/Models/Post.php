<?php

namespace App\Domain\Blog\Models;

use App\Domain\Blog\Enums\PostStatus;
use App\Domain\Identity\Models\User;
use Database\Factories\Domain\Blog\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'blog_posts';

    /**
     * @return PostFactory
     */
    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }

    protected $fillable = [
        'author_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'status',
        'published_at',
        'featured_media_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(BlogMedia::class, 'featured_media_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'blog_category_post', 'post_id', 'category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'blog_post_tag', 'post_id', 'tag_id');
    }

    public function scopeByStatus(Builder $query, ?string $status): Builder
    {
        if ($status === null || $status === '') {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || $search === '') {
            return $query;
        }

        $term = mb_strtolower($search);

        return $query->where(function (Builder $q) use ($term): void {
            $q->whereRaw('LOWER(title) LIKE ?', ['%'.$term.'%'])
                ->orWhereRaw('LOWER(slug) LIKE ?', ['%'.$term.'%']);
        });
    }
}
