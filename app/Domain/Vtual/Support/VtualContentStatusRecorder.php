<?php

namespace App\Domain\Vtual\Support;

use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Models\ContentStatusEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records the status timeline of a content row (decision: reference-metrics.md).
 *
 * **A content row is a live session.** The platform's stream session id is the
 * content `external_id`, so the lifecycle of one row *is* the lifecycle of one
 * broadcast; a restream arrives as a different `external_id` and is therefore a
 * different session. That is why this table has no session id of its own and why
 * nothing here correlates rows: correlation is already a primary key.
 *
 * Transitions only. A stream that stays `live` for six hours is polled ~180 times
 * and produces exactly one `live` row, because the recorder compares the incoming
 * status with the stored one and writes nothing when they match. The caller is
 * always a path that already knows the new status — hydrate, end-of-stream, or
 * the tombstone — so this adds no query to the ingest hot path beyond the one it
 * is already running.
 *
 * Best-effort like the metric recorder: history must never fail the hydrate that
 * carries it, so every failure is reported and swallowed.
 */
class VtualContentStatusRecorder
{
    public function isEnabled(): bool
    {
        return (bool) setting_config('vtual.metrics.enabled', true);
    }

    /**
     * Record a transition when the status actually moved.
     *
     * @param  ContentStatus|null  $previousStatus  Null on a first observation.
     */
    public function record(
        Content $content,
        ContentStatus $status,
        ?ContentStatus $previousStatus = null,
        ?CarbonInterface $at = null,
    ): ?ContentStatusEvent {
        if (! $this->isEnabled()) {
            return null;
        }

        if ($previousStatus !== null && $previousStatus === $status) {
            return null;
        }

        $occurredAt = $at !== null ? Carbon::instance($at) : now();

        try {
            $event = new ContentStatusEvent([
                'content_id' => (string) $content->id,
                'channel_id' => (string) $content->channel_id,
                'platform_id' => (string) $content->platform_id,
                'creator_id' => $content->creator_id !== null ? (string) $content->creator_id : null,
                'status' => $status,
                'previous_status' => $previousStatus,
                'occurred_at' => $occurredAt,
                'created_at' => now(),
            ]);
            $event->id = (string) Str::uuid7();
            $event->save();

            return $event;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Retention window in days — shared with the metric history on purpose: the
     * timeline and the samples that annotate it are only useful together.
     */
    public function retentionDays(): int
    {
        return max(1, (int) setting_config('vtual.metrics.retention_days', 90));
    }

    /**
     * Latest recorded status for a content row, if any. Used by callers that did
     * not load the row before mutating it (the end-of-stream bulk update).
     */
    public function latestStatus(string $contentId): ?ContentStatus
    {
        $status = DB::table('vtual_content_status_events')
            ->where('content_id', $contentId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->value('status');

        if ($status === null) {
            return null;
        }

        return ContentStatus::tryFrom((string) $status);
    }
}
