<?php

namespace App\Domain\Blog\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogMedia extends Model
{
    use HasUuids;

    protected $table = 'blog_media';

    protected $fillable = [
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'width',
        'height',
    ];

    /**
     * @return array<string, string|int>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Publicly usable URL for <img> insertion in the editor.
     */
    public function url(): string
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $storage */
        $storage = \Illuminate\Support\Facades\Storage::disk($this->disk);

        return $storage->url($this->path);
    }
}
