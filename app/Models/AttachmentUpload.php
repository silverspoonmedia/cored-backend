<?php

namespace App\Models;

use Database\Factories\AttachmentUploadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tracks a browser-direct upload session to an S3-compatible disk.
 *
 * The client uploads bytes straight to object storage via presigned URLs; this
 * row is the server-side source of truth for path, multipart state, and whether
 * the object is safe to attach to an order.
 */
#[Fillable([
    'disk',
    'path',
    's3_upload_id',
    'strategy',
    'original_name',
    'mime',
    'size',
    'status',
    'parts',
    'user_id',
    'expires_at',
    'completed_at',
])]
class AttachmentUpload extends Model
{
    /** @use HasFactory<AttachmentUploadFactory> */
    use HasFactory, HasUuids;

    public const STRATEGY_SINGLE = 'single';

    public const STRATEGY_MULTIPART = 'multipart';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ABORTED = 'aborted';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'parts' => 'array',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * @return list<array{part_number: int, etag: string, size: int}>
     */
    public function recordedParts(): array
    {
        return array_values($this->parts ?? []);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', Carbon::now());
    }
}
