<?php

namespace App\Domain\Archivd\Models;

use App\Domain\Archivd\Enums\FileVariantKind;
use App\Domain\Archivd\Enums\FileVariantStatus;
use App\Support\Product\Services\ProductAnalyticsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileVariant extends Model
{
    use HasUuids;

    protected $table = 'archivd_file_variants';

    protected $fillable = [
        'file_id',
        'kind',
        'disk',
        'path',
        'size',
        'max_px',
        'mime',
        'status',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'max_px' => 'integer',
            'status' => FileVariantStatus::class,
            'kind' => FileVariantKind::class,
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (FileVariant $variant): void {
            if (! $variant->wasChanged('status')) {
                return;
            }

            if ($variant->status !== FileVariantStatus::Ready) {
                return;
            }

            $variant->loadMissing('file');
            if ($variant->file === null) {
                return;
            }

            app(ProductAnalyticsService::class)->increment(
                'Archivd',
                $variant->file->user_id,
                'storage_used_bytes',
                (int) $variant->size,
            );
        });

        static::deleted(function (FileVariant $variant): void {
            if ($variant->status !== FileVariantStatus::Ready) {
                return;
            }

            $variant->loadMissing('file');
            if ($variant->file === null) {
                return;
            }

            app(ProductAnalyticsService::class)->decrement(
                'Archivd',
                $variant->file->user_id,
                'storage_used_bytes',
                (int) $variant->size,
            );
        });
    }

    /**
     * @return BelongsTo<HostedFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(HostedFile::class, 'file_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', FileVariantStatus::Ready);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeKind(Builder $query, FileVariantKind|string $kind): Builder
    {
        $value = $kind instanceof FileVariantKind ? $kind->value : $kind;

        return $query->where('kind', $value);
    }
}
