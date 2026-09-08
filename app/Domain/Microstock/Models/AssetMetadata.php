<?php

namespace App\Domain\Microstock\Models;

use App\Domain\Archivd\Models\HostedFile;
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
        'billing_user_id',
        'analysis_run_id',
        'billing_source',
        'title',
        'keywords',
        'category',
        'description',
        'status',
        'ai_raw',
        'ai_vision',
        'ai_thinking',
        'ai_result',
        'ai_billing',
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
            'ai_vision' => 'array',
            'ai_thinking' => 'array',
            'ai_result' => 'array',
            'ai_billing' => 'array',
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
