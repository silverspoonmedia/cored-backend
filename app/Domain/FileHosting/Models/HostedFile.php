<?php

namespace App\Domain\FileHosting\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'visibility',
        'download_count',
        'view_count',
        'last_downloaded_at',
        'last_viewed_at',
        'signed_download_url',
        'signed_download_expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'download_count' => 'integer',
            'view_count' => 'integer',
            'last_downloaded_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'signed_download_expires_at' => 'datetime',
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

    public function hasUsableSignedDownloadUrl(): bool
    {
        if ($this->signed_download_url === null || $this->signed_download_url === '') {
            return false;
        }

        if ($this->signed_download_expires_at === null) {
            return false;
        }

        $margin = (int) config('file-hosting.signed_download_cache_margin_seconds', 60);

        return $this->signed_download_expires_at->greaterThan(now()->addSeconds($margin));
    }

    public function clearSignedDownloadCache(): void
    {
        $this->forceFill([
            'signed_download_url' => null,
            'signed_download_expires_at' => null,
        ])->save();
    }
}
