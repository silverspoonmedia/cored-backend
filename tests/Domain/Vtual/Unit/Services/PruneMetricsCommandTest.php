<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\ChannelMetric;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Models\ContentMetric;
use App\Domain\Vtual\Models\ContentStatusEvent;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\PlatformService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The history tables are append-only and grow with every refresh, so retention is
 * enforced by a command rather than by the writers. Pruning is keyed on
 * `captured_at` (when the value was observed), not on `bucket_start`, so a
 * backfilled sample cannot survive on a technicality.
 */
class PruneMetricsCommandTest extends TestCase
{
    use RefreshDatabase;

    private Platform $youtube;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        app(PlatformService::class)->seedDefaults();
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->channel = $this->seedChannel();
    }

    #[Test]
    public function prunes_channel_samples_older_than_the_window(): void
    {
        $old = $this->channelSample(now()->subDays(120));
        $young = $this->channelSample(now()->subDays(10));

        $this->artisan('vtual:prune-metrics', ['--days' => 90])->assertSuccessful();

        $this->assertNull(ChannelMetric::find($old->id));
        $this->assertNotNull(ChannelMetric::find($young->id));
    }

    #[Test]
    public function prunes_content_samples_too(): void
    {
        $content = $this->seedContent();
        $old = $this->contentSample($content, now()->subDays(120));
        $young = $this->contentSample($content, now()->subDays(2));

        $this->artisan('vtual:prune-metrics', ['--days' => 90])->assertSuccessful();

        $this->assertNull(ContentMetric::find($old->id));
        $this->assertNotNull(ContentMetric::find($young->id));
    }

    #[Test]
    public function the_window_defaults_to_the_tier_b_setting(): void
    {
        config(['vtual.metrics.retention_days' => 30]);
        $old = $this->channelSample(now()->subDays(45));
        $young = $this->channelSample(now()->subDays(3));

        $this->artisan('vtual:prune-metrics')->assertSuccessful();

        $this->assertNull(ChannelMetric::find($old->id));
        $this->assertNotNull(ChannelMetric::find($young->id));
    }

    #[Test]
    public function prunes_status_events_on_the_same_window(): void
    {
        $content = $this->seedContent();
        $old = $this->statusEvent($content, now()->subDays(120));
        $young = $this->statusEvent($content, now()->subDays(2));

        $this->artisan('vtual:prune-metrics', ['--days' => 90])->assertSuccessful();

        // A transition with no samples left to annotate it is not a report
        // anyone reads, so the timeline shares the metric window.
        $this->assertNull(ContentStatusEvent::find($old->id));
        $this->assertNotNull(ContentStatusEvent::find($young->id));
    }

    #[Test]
    public function archiving_a_channel_keeps_its_history(): void
    {
        $this->channelSample(now());

        // Channel rows soft-delete, so archiving must not erase the timeline: the
        // growth history of a retired channel is still a report someone reads.
        $this->channel->delete();

        $this->assertSame(1, ChannelMetric::query()->count());
    }

    #[Test]
    public function a_hard_deleted_channel_takes_its_history_with_it(): void
    {
        $this->channelSample(now());

        // The FK cascades on a real delete: a metric row is a number *about* that
        // channel, and an unattributable row is worse than no row.
        $this->channel->forceDelete();

        $this->assertSame(0, ChannelMetric::query()->count());
    }

    private function channelSample(Carbon $capturedAt): ChannelMetric
    {
        $row = new ChannelMetric([
            'channel_id' => $this->channel->id,
            'platform_id' => $this->channel->platform_id,
            'creator_id' => $this->channel->creator_id,
            'subscriber_count' => 1234,
            'bucket_start' => $capturedAt->copy()->startOfMinute(),
            'captured_at' => $capturedAt,
            'created_at' => $capturedAt,
        ]);
        $row->id = (string) Str::uuid7();
        $row->save();

        return $row;
    }

    private function contentSample(Content $content, Carbon $capturedAt): ContentMetric
    {
        $row = new ContentMetric([
            'content_id' => $content->id,
            'channel_id' => $content->channel_id,
            'platform_id' => $content->platform_id,
            'creator_id' => $content->creator_id,
            'concurrent_viewers' => 500,
            'bucket_start' => $capturedAt->copy()->startOfMinute(),
            'captured_at' => $capturedAt,
            'created_at' => $capturedAt,
        ]);
        $row->id = (string) Str::uuid7();
        $row->save();

        return $row;
    }

    private function statusEvent(Content $content, Carbon $occurredAt): ContentStatusEvent
    {
        $row = new ContentStatusEvent([
            'content_id' => $content->id,
            'channel_id' => $content->channel_id,
            'platform_id' => $content->platform_id,
            'creator_id' => $content->creator_id,
            'status' => ContentStatus::Live,
            'previous_status' => ContentStatus::Unknown,
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
        ]);
        $row->id = (string) Str::uuid7();
        $row->save();

        return $row;
    }

    private function seedChannel(): Channel
    {
        $creator = Creator::query()->create([
            'slug' => 'prune-metric-'.Str::lower(Str::random(8)),
            'display_name' => 'Prune Metric',
            'status' => EntityStatus::Active,
        ]);

        return Channel::query()->create([
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UCPRUNEMETRIC',
            'handle' => 'prune-metric',
            'display_name' => 'Prune Metric',
            'status' => ChannelStatus::Active,
        ]);
    }

    private function seedContent(): Content
    {
        $content = new Content([
            'creator_id' => $this->channel->creator_id,
            'channel_id' => $this->channel->id,
            'platform_id' => $this->channel->platform_id,
            'external_id' => 'prune-live-1',
            'type' => ContentType::Live,
            'status' => ContentStatus::Live,
            'title' => 'Prune live',
            'url' => 'https://example.test/live',
        ]);
        $content->id = (string) Str::uuid7();
        $content->save();

        return $content;
    }
}
