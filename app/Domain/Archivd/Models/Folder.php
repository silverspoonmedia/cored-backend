<?php

namespace App\Domain\Archivd\Models;

use App\Domain\Archivd\Enums\CollectionStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Folder extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'archivd_folders';

    protected $fillable = [
        'user_id',
        'workspace_id',
        'parent_id',
        'name',
        'order',
        'visibility',
        'collection_status',
        'collection_hint',
        'collection_locked_at',
        'collection_csv_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'collection_status' => CollectionStatus::class,
            'collection_locked_at' => 'datetime',
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
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function isWorkspaceResource(): bool
    {
        return $this->workspace_id !== null;
    }

    /**
     * A locked collection no longer accepts uploads (status ∈ locked,
     * processing, review, exported). NULL/draft/failed stay open.
     */
    public function isCollectionLocked(): bool
    {
        return $this->collection_status?->isLockedForUpload() ?? false;
    }

    public function markCollectionStatus(CollectionStatus $status): void
    {
        $this->forceFill(['collection_status' => $status])->save();
    }

    /**
     * @return HasMany<Folder, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    /**
     * @return HasMany<HostedFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(HostedFile::class, 'folder_id');
    }
}
