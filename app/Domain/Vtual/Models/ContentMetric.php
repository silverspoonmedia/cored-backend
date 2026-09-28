<?php

namespace App\Domain\Vtual\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One concurrent-viewer sample for a content row (decision: reference-metrics.md).
 *
 * Only live rows are sampled — a VOD has no CCV to track. Same append-only
 * contract as {@see ChannelMetric}: `(content_id, bucket_start)` is unique, so
 * the poll and the EventSub push for the same minute produce one row.
 */
class ContentMetric extends Model
{
    use HasUuids;

    protected $table = 'vtual_content_metrics';

    public $timestamps = false;

    protected $fillable = [
        'content_id',
        'channel_id',
        'platform_id',
        'creator_id',
        'concurrent_viewers',
        'bucket_start',
        'captured_at',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'concurrent_viewers' => 'integer',
            'bucket_start' => 'datetime',
            'captured_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Content, $this>
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'content_id');
    }

    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    /**
     * @return BelongsTo<Platform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class, 'platform_id');
    }

    /**
     * @return BelongsTo<Creator, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class, 'creator_id');
    }
}
