<?php

namespace App\Domain\Vtual\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One subscriber-count sample for a channel (decision: reference-metrics.md).
 *
 * Append-only history: rows are never updated, and the unique key
 * `(channel_id, bucket_start)` is what collapses a burst of refreshes inside one
 * sampling bucket into a single row. The live `subscriber_count` column on
 * `vtual_channels` stays the current value; this table is the timeline.
 */
class ChannelMetric extends Model
{
    use HasUuids;

    protected $table = 'vtual_channel_metrics';

    public $timestamps = false;

    protected $fillable = [
        'channel_id',
        'platform_id',
        'creator_id',
        'subscriber_count',
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
            'subscriber_count' => 'integer',
            'bucket_start' => 'datetime',
            'captured_at' => 'datetime',
            'created_at' => 'datetime',
        ];
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
