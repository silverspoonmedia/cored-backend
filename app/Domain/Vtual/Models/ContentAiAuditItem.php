<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Vtual\Enums\ContentAiAuditStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One queued AI classification audit for a content row.
 *
 * Written by ContentAiAuditService::enqueueIfEligible (hydrate path) and drained
 * by the `vtual:audit-content-ai` command. Purely a work queue — never read by
 * the public API.
 */
class ContentAiAuditItem extends Model
{
    use HasUuids;

    protected $table = 'vtual_content_ai_audit';

    protected $fillable = [
        'content_id',
        'status',
        'attempts',
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
            'status' => ContentAiAuditStatus::class,
            'attempts' => 'integer',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Content, $this>
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'content_id');
    }
}
