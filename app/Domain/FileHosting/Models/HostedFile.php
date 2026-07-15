<?php

namespace App\Domain\FileHosting\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class HostedFile extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'file_hosting_files';

    protected $fillable = [
        'user_id',
        'folder_id',
        'name',
        'disk',
        'path',
        'size',
        'mime',
        'checksum',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'folder_id');
    }

    public function temporaryDownloadUrl(?int $ttlMinutes = null): ?string
    {
        $ttl = $ttlMinutes ?? (int) config('file-hosting.signed_download_ttl_minutes', 15);
        $disk = Storage::disk($this->disk);

        if (! method_exists($disk, 'temporaryUrl')) {
            return null;
        }

        return $disk->temporaryUrl($this->path, now()->addMinutes($ttl));
    }
}
