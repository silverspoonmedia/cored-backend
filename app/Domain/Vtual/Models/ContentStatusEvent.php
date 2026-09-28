<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Vtual\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One observed status transition of a content row (decision: reference-metrics.md).
 *
 * A content row **is** a live session: the platform stream session id is the
 * content `external_id`, so the timeline of one row is the lifecycle of one
 * broadcast, and a restream shows up as a different row rather than a second
 * session on the same one.
 */
class ContentStatusEvent extends Model
{
    use HasUuids;

    protected $table = 'vtual_content_status_events';

    public $timestamps = false;

    protected $fillable = [
        'content_id',
        'channel_id',
        'platform_id',
        'creator_id',
        'status',
        'previous_status',
        'occurred_at',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'previous_status' => ContentStatus::class,
            'occurred_at' => 'datetime',
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
