<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Vtual\Enums\ContentFetchPoolSource;
use App\Domain\Vtual\Enums\ContentFetchPoolStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentFetchPoolItem extends Model
{
    use HasUuids;

    protected $table = 'vtual_content_fetch_pool';

    protected $fillable = [
        'platform_id',
        'video_external_id',
        'channel_id',
        'source',
        'status',
        'requested_at',
        'processed_at',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => ContentFetchPoolSource::class,
            'status' => ContentFetchPoolStatus::class,
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Platform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class, 'platform_id');
    }

    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }
}
