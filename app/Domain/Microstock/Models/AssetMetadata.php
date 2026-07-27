<?php

namespace App\Domain\Microstock\Models;

use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\MetadataStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMetadata extends Model
{
    use HasUuids;

    protected $table = 'microstock_asset_metadata';

    protected $fillable = [
        'file_id',
        'user_id',
        'title',
        'keywords',
        'category',
        'description',
        'status',
        'ai_raw',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'ai_raw' => 'array',
            'status' => MetadataStatus::class,
        ];
    }

    /**
     * @return BelongsTo<HostedFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(HostedFile::class, 'file_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isFinalized(): bool
    {
        return $this->status === MetadataStatus::Finalized;
    }
}
