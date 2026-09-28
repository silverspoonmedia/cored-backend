<?php

namespace App\Domain\Microstock\Models;

use App\Domain\Archivd\Models\Folder;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\MarketplaceUploadStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One marketplace upload attempt (decision:
 * docs/domains/microstock/reference-marketplace-upload.md).
 *
 * The upload copies finalized files out to a marketplace; nothing local is
 * deleted, so this row is the audit trail and the status the SPA renders.
 */
class MarketplaceUpload extends Model
{
    use HasUuids;

    protected $table = 'microstock_marketplace_uploads';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'folder_id',
        'credential_id',
        'marketplace',
        'status',
        'remote_path',
        'file_count',
        'byte_count',
        'error',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MarketplaceUploadStatus::class,
            'file_count' => 'integer',
            'byte_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
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

    /**
     * @return BelongsTo<MarketplaceCredential, $this>
     */
    public function credential(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCredential::class, 'credential_id');
    }
}
