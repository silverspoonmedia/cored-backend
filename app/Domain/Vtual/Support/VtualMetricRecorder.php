<?php

namespace App\Domain\Vtual\Support;

use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Content;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes historical metric snapshots (decision: docs/domains/vtual/reference-metrics.md).
 *
 * The point-in-time columns (`vtual_channels.subscriber_count`,
 * `vtual_contents.concurrent_viewers`) are overwritten on every refresh. This
 * recorder appends the value to `vtual_channel_metrics` / `vtual_content_metrics`
 * so the timeline survives, called from the writers that already hold a fresh
 * value — channel fetch-pool flush, channel activation, claim-verify refresh and
 * content hydrate. No new HTTP, no new scheduled fetch.
 *
 * Sampling is bucketed, not throttled: the row key is
 * `(subject_id, bucket_start)`, and `insertOrIgnore` on the unique index means a
 * burst of pushes inside one bucket produces exactly one row — no lock, no read,
 * no "did we already sample?" state to keep. Buckets are floored, never rounded,
 * so a bucket always starts on a wall-clock boundary.
 *
 * A null metric is skipped rather than stored: a hidden subscriber count would
 * otherwise occupy the bucket and shadow the real value that arrives later in
 * the same window.
 *
 * History is best-effort — a metric write must never fail a hydrate or a pool
 * flush, so every failure is logged and swallowed. The tables are pruned by
 * `vtual:prune-metrics`.
 */
class VtualMetricRecorder
{
    public function isEnabled(): bool
    {
        return (bool) setting_config('vtual.metrics.enabled', true);
    }

    /**
     * Append a subscriber-count sample for a channel.
     *
     * The value defaults to the channel's own column — callers write the column
     * first and then hand over the row, so the two can never disagree.
     */
    public function recordChannel(Channel $channel, ?int $subscriberCount = null, ?CarbonInterface $at = null): void
    {
        $value = $subscriberCount ?? ($channel->subscriber_count !== null ? (int) $channel->subscriber_count : null);

        if (! $this->isEnabled() || $value === null) {
            return;
        }

        $capturedAt = $this->resolveCapturedAt($at);

        $this->write('vtual_channel_metrics', [
            'channel_id' => (string) $channel->id,
            'platform_id' => (string) $channel->platform_id,
            'creator_id' => $channel->creator_id !== null ? (string) $channel->creator_id : null,
            'subscriber_count' => max(0, $value),
        ], $capturedAt, $this->bucketMinutes('vtual.metrics.channel_interval_minutes', 15));
    }

    /**
     * Append a concurrent-viewer sample for a live content row. Non-live rows are
     * ignored: a VOD has no CCV to track, and the caller's fresh hydrate may be
     * for either. The value defaults to the row's own column.
     */
    public function recordContent(Content $content, ?int $concurrentViewers = null, ?CarbonInterface $at = null): void
    {
        $value = $concurrentViewers ?? ($content->concurrent_viewers !== null ? (int) $content->concurrent_viewers : null);

        if (! $this->isEnabled() || $value === null) {
            return;
        }

        if ($content->type !== ContentType::Live || $content->status !== ContentStatus::Live) {
            return;
        }

        $capturedAt = $this->resolveCapturedAt($at);

        $this->write('vtual_content_metrics', [
            'content_id' => (string) $content->id,
            'channel_id' => (string) $content->channel_id,
            'platform_id' => (string) $content->platform_id,
            'creator_id' => $content->creator_id !== null ? (string) $content->creator_id : null,
            'concurrent_viewers' => max(0, $value),
        ], $capturedAt, $this->bucketMinutes('vtual.metrics.content_interval_minutes', 1));
    }

    /**
     * Retention window in days, shared by both tables.
     */
    public function retentionDays(): int
    {
        return max(1, (int) setting_config('vtual.metrics.retention_days', 90));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function write(string $table, array $attributes, Carbon $capturedAt, int $bucketMinutes): void
    {
        $bucketStart = $capturedAt->copy()->startOfMinute();

        // Floor to the bucket, keeping the wall-clock boundary (e.g. 15-minute
        // buckets start at :00, :15, :30, :45 — not at an arbitrary offset).
        $bucketStart = $bucketStart->copy()->subMinutes($bucketStart->minute % $bucketMinutes);

        try {
            DB::table($table)->insertOrIgnore(array_merge($attributes, [
                'id' => (string) Str::uuid7(),
                'bucket_start' => $bucketStart,
                'captured_at' => $capturedAt,
                'created_at' => now(),
            ]));
        } catch (Throwable $e) {
            // Best-effort history: a broken metrics write must not fail the
            // hydrate / pool flush that owns the transaction.
            report($e);
        }
    }

    private function bucketMinutes(string $key, int $default): int
    {
        return max(1, (int) setting_config($key, $default));
    }

    private function resolveCapturedAt(?CarbonInterface $at): Carbon
    {
        return $at !== null ? Carbon::instance($at) : now();
    }
}
