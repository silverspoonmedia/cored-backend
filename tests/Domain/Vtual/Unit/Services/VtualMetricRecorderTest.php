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
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\PlatformService;
use App\Domain\Vtual\Support\VtualMetricRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The recorder is the only writer of the metric history tables, and its contract
 * is deliberately narrow:
 *
 * - **bucketed, not throttled** — `insertOrIgnore` on `(subject, bucket_start)`
 *   means a burst of pushes inside one bucket collapses to one row, with no
 *   read-then-write and no lock;
 * - **null is skipped, not stored** — a hidden subscriber count would otherwise
 *   occupy the bucket and shadow the real value arriving later in the window;
 * - **best-effort** — a metric write must never fail the hydrate or pool flush
 *   that owns the transaction.
 */
class VtualMetricRecorderTest extends TestCase
{
    use RefreshDatabase;

    private VtualMetricRecorder $recorder;

    private Platform $youtube;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        app(PlatformService::class)->seedDefaults();
        $this->recorder = app(VtualMetricRecorder::class);
        $this->youtube = Platform::query()->where('key', 'youtube')->firstOrFail();
        $this->channel = $this->seedChannel();
        config([
            'vtual.metrics.enabled' => true,
            'vtual.metrics.channel_interval_minutes' => 15,
            'vtual.metrics.content_interval_minutes' => 1,
            'vtual.metrics.retention_days' => 90,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function channel_sample_stores_the_value_with_its_subject(): void
    {
        Carbon::setTestNow('2026-09-27 10:07:33');

        $this->recorder->recordChannel($this->channel, 42000);

        $row = ChannelMetric::query()->sole();
        $this->assertSame((string) $this->channel->id, $row->channel_id);
        $this->assertSame((string) $this->channel->platform_id, $row->platform_id);
        $this->assertSame((string) $this->channel->creator_id, $row->creator_id);
        $this->assertSame(42000, $row->subscriber_count);
        $this->assertSame('2026-09-27 10:00:00', $row->bucket_start?->toDateTimeString());
        $this->assertSame('2026-09-27 10:07:33', $row->captured_at?->toDateTimeString());
    }

    #[Test]
    public function bucket_boundaries_are_floored_to_the_interval(): void
    {
        Carbon::setTestNow('2026-09-27 10:29:59');

        $this->recorder->recordChannel($this->channel, 1);

        // 15-minute buckets start at :00/:15/:30/:45 — never at an offset that
        // would drift with the first write of the day.
        $this->assertSame(
            '2026-09-27 10:15:00',
            ChannelMetric::query()->sole()->bucket_start?->toDateTimeString(),
        );
    }

    #[Test]
    public function a_burst_inside_one_bucket_collapses_to_a_single_row(): void
    {
        Carbon::setTestNow('2026-09-27 10:00:05');
        $this->recorder->recordChannel($this->channel, 100);

        Carbon::setTestNow('2026-09-27 10:05:00');
        $this->recorder->recordChannel($this->channel, 200);

        Carbon::setTestNow('2026-09-27 10:14:59');
        $this->recorder->recordChannel($this->channel, 300);

        // The first sample of the bucket wins: the later writes are ignored by
        // the unique index, so the row never flickers between values.
        $this->assertSame(1, ChannelMetric::query()->count());
        $this->assertSame(100, ChannelMetric::query()->sole()->subscriber_count);
    }

    #[Test]
    public function the_next_bucket_appends_a_new_row(): void
    {
        Carbon::setTestNow('2026-09-27 10:00:05');
        $this->recorder->recordChannel($this->channel, 100);

        Carbon::setTestNow('2026-09-27 10:15:01');
        $this->recorder->recordChannel($this->channel, 150);

        $this->assertSame(2, ChannelMetric::query()->count());
        $this->assertSame(
            [100, 150],
            ChannelMetric::query()->orderBy('bucket_start')->pluck('subscriber_count')->all(),
        );
    }

    #[Test]
    public function a_null_subscriber_count_is_skipped_rather_than_stored(): void
    {
        // The column itself is the value when the caller does not pass one, so a
        // channel whose count the platform hides must produce no row at all.
        $this->channel->forceFill(['subscriber_count' => null])->save();

        $this->recorder->recordChannel($this->channel->refresh());

        // Storing the null would occupy the bucket and hide the real value that
        // the same window may still deliver.
        $this->assertSame(0, ChannelMetric::query()->count());
    }

    #[Test]
    public function content_samples_are_only_written_for_live_rows(): void
    {
        $live = $this->seedContent(ContentType::Live, ContentStatus::Live, 1200);
        $vod = $this->seedContent(ContentType::Vod, ContentStatus::Ended, 900);
        $archiveLive = $this->seedContent(ContentType::Archive, ContentStatus::Live, 700);

        $this->recorder->recordContent($live, 1200);
        $this->recorder->recordContent($vod, 900);
        $this->recorder->recordContent($archiveLive, 700);

        $row = ContentMetric::query()->sole();
        $this->assertSame((string) $live->id, $row->content_id);
        $this->assertSame(1200, $row->concurrent_viewers);
    }

    #[Test]
    public function a_live_row_without_a_viewer_count_is_skipped(): void
    {
        $live = $this->seedContent(ContentType::Live, ContentStatus::Live, null);

        $this->recorder->recordContent($live);

        $this->assertSame(0, ContentMetric::query()->count());
    }

    #[Test]
    public function content_buckets_are_per_minute(): void
    {
        Carbon::setTestNow('2026-09-27 10:00:30');
        $live = $this->seedContent(ContentType::Live, ContentStatus::Live, 500);
        $this->recorder->recordContent($live, 500);

        Carbon::setTestNow('2026-09-27 10:00:59');
        $this->recorder->recordContent($live, 640);

        $this->assertSame(1, ContentMetric::query()->count());

        Carbon::setTestNow('2026-09-27 10:01:00');
        $this->recorder->recordContent($live, 700);

        $this->assertSame(2, ContentMetric::query()->count());
    }

    #[Test]
    public function metrics_are_disabled_by_the_tier_b_setting(): void
    {
        config(['vtual.metrics.enabled' => false]);
        $live = $this->seedContent(ContentType::Live, ContentStatus::Live, 100);

        $this->recorder->recordChannel($this->channel, 100);
        $this->recorder->recordContent($live, 100);

        $this->assertSame(0, ChannelMetric::query()->count());
        $this->assertSame(0, ContentMetric::query()->count());
    }

    #[Test]
    public function the_retention_window_comes_from_configuration(): void
    {
        config(['vtual.metrics.retention_days' => 30]);

        $this->assertSame(30, $this->recorder->retentionDays());
    }

    #[Test]
    public function an_explicit_capture_time_can_be_supplied(): void
    {
        $at = Carbon::parse('2026-09-27 09:03:10');

        $this->recorder->recordChannel($this->channel, 77, $at);

        $row = ChannelMetric::query()->sole();
        $this->assertSame('2026-09-27 09:00:00', $row->bucket_start?->toDateTimeString());
        $this->assertSame('2026-09-27 09:03:10', $row->captured_at?->toDateTimeString());
    }

    private function seedChannel(): Channel
    {
        $creator = Creator::query()->create([
            'slug' => 'metric-'.Str::lower(Str::random(8)),
            'display_name' => 'Metric Owner',
            'status' => EntityStatus::Active,
        ]);

        return Channel::query()->create([
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UCMETRIC'.Str::upper(Str::random(6)),
            'handle' => 'metric-'.Str::lower(Str::random(6)),
            'display_name' => 'Metric Channel',
            'status' => ChannelStatus::Active,
            'subscriber_count' => 1000,
        ]);
    }

    private function seedContent(ContentType $type, ContentStatus $status, ?int $ccv): Content
    {
        $content = new Content([
            'creator_id' => $this->channel->creator_id,
            'channel_id' => $this->channel->id,
            'platform_id' => $this->channel->platform_id,
            'external_id' => 'vid-'.Str::lower(Str::random(10)),
            'type' => $type,
            'status' => $status,
            'title' => 'Sample',
            'url' => 'https://example.test/watch',
            'concurrent_viewers' => $ccv,
        ]);
        $content->id = (string) Str::uuid7();
        $content->save();

        return $content;
    }
}
