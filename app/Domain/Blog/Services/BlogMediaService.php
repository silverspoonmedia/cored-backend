<?php

namespace App\Domain\Blog\Services;

use App\Domain\Blog\Models\BlogMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogMediaService
{
    public function store(UploadedFile $file, string $uploadedBy): BlogMedia
    {
        $disk = config('blog.media.disk', 'public');
        $directory = trim((string) config('blog.media.directory', 'blog'), '/');

        $path = $file->storeAs(
            $directory,
            Str::uuid()->toString().'.'.strtolower($file->getClientOriginalExtension() ?: 'bin'),
            $disk,
        );

        $dimensions = $this->dimensions($file);

        return BlogMedia::query()->create([
            'uploaded_by' => $uploadedBy,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?? 0,
            'width' => $dimensions[0],
            'height' => $dimensions[1],
        ]);
    }

    public function delete(BlogMedia $media): void
    {
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function dimensions(UploadedFile $file): array
    {
        try {
            $info = getimagesize($file->getRealPath());

            if ($info === false) {
                return [null, null];
            }

            return [(int) $info[0], (int) $info[1]];
        } catch (\Throwable) {
            return [null, null];
        }
    }
}
