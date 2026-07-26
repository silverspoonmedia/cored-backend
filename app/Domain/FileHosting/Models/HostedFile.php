<?php

namespace App\Domain\FileHosting\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Support\Product\Services\ProductAnalyticsService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class HostedFile extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'file_hosting_files';

    protected $fillable = [
        'user_id',
        'workspace_id',
        'created_by',
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
     * Analytics hooks: keep product_analytics counters in sync on file
     * lifecycle. `deleted` fires for both soft and force deletes. `restored`
     * fires on model-instance restore (not bulk query restore — that blind
     * spot is handled explicitly in FolderRepository::restore).
     */
    protected static function booted(): void
    {
        static::created(function (HostedFile $file): void {
            app(ProductAnalyticsService::class)->increment(
                'FileHosting',
                $file->user_id,
                'storage_used_bytes',
                (int) $file->size,
            );
            app(ProductAnalyticsService::class)->increment(
                'FileHosting',
                $file->user_id,
                'file_count',
                1,
            );
        });

        static::deleted(function (HostedFile $file): void {
            app(ProductAnalyticsService::class)->decrement(
                'FileHosting',
                $file->user_id,
                'storage_used_bytes',
                (int) $file->size,
            );
            app(ProductAnalyticsService::class)->decrement(
                'FileHosting',
                $file->user_id,
                'file_count',
                1,
            );
        });

        static::restored(function (HostedFile $file): void {
            app(ProductAnalyticsService::class)->increment(
                'FileHosting',
                $file->user_id,
                'storage_used_bytes',
                (int) $file->size,
            );
            app(ProductAnalyticsService::class)->increment(
                'FileHosting',
                $file->user_id,
                'file_count',
                1,
            );
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'folder_id');
    }

    /**
     * @return HasOne<AssetMetadata, $this>
     */
    public function microstockMetadata(): HasOne
    {
        return $this->hasOne(AssetMetadata::class, 'file_id');
    }

    public function isWorkspaceResource(): bool
    {
        return $this->workspace_id !== null;
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
