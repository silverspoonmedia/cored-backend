<?php

namespace App\Domain\FileHosting\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class FileUpload extends Model
{
    use HasUuids;

    protected $table = 'file_hosting_uploads';

    public const STRATEGY_SINGLE = 'single';

    public const STRATEGY_MULTIPART = 'multipart';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ABORTED = 'aborted';

    protected $fillable = [
        'user_id',
        'workspace_id',
        'folder_id',
        'disk',
        'path',
        's3_upload_id',
        'strategy',
        'original_name',
        'mime',
        'size',
        'content_hash',
        'chunk_size',
        'part_count',
        'status',
        'parts',
        'promoted_file_id',
        'expires_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'chunk_size' => 'integer',
            'part_count' => 'integer',
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

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'folder_id');
    }

    /**
     * @return BelongsTo<HostedFile, $this>
     */
    public function promotedFile(): BelongsTo
    {
        return $this->belongsTo(HostedFile::class, 'promoted_file_id');
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

    public function isPromoted(): bool
    {
        return $this->promoted_file_id !== null;
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

    /**
     * Pending, not-yet-expired, not-yet-promoted multipart sessions that match a
     * client-supplied content fingerprint + size for the same storage owner.
     *
     * Used by resume: a re-upload of the same file reuses this session instead of
     * creating a fresh one (and stranding the old multipart as an orphan).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeResumableFor(Builder $query, string $userId, string $contentHash, int $size): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->where('content_hash', $contentHash)
            ->where('size', $size)
            ->where('strategy', self::STRATEGY_MULTIPART)
            ->where('status', self::STATUS_PENDING)
            ->whereNull('promoted_file_id')
            ->where('expires_at', '>', Carbon::now());
    }
}
