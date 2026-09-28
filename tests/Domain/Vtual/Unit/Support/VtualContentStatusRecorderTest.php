<?php

namespace Tests\Domain\Vtual\Unit\Support;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Models\ContentStatusEvent;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Services\ContentService;
use App\Domain\Vtual\Services\PlatformService;
use App\Domain\Vtual\Support\VtualContentStatusRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The status timeline is a *transition* log, not a poll log. A stream that stays
 * live for six hours is polled ~180 times; the table must show two rows. Each
 * test here pins one half of that contract:
 *
 * - the recorder writes nothing when the status did not move,
 * - and the hydrate / end-of-stream / tombstone seams pass the previous status
 *   through, so "did not move" is detectable at all.
 *
 * The session definition itself is structural and asserted too: the platform
 * stream session id is the content `external_id`, so one content row is one
 * broadcast and a restream is a different row.
 */
class VtualContentStatusRecorderTest extends TestCase
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
        config(['vtual.metrics.enabled' => true]);
        Bus::fake();
    }

    #[Test]
    public function a_transition_writes_one_row_with_both_states(): void
    {
        $content = $this->seedContent('session-1', ContentType::Live, ContentStatus::Live);

        $event = app(VtualContentStatusRecorder::class)->record(
            $content,
            ContentStatus::Ended,
            ContentStatus::Live,
        );

        $this->assertNotNull($event);
        $this->assertSame(ContentStatus::Ended, $event?->status);
        $this->assertSame(ContentStatus::Live, $event?->previous_status);
        $this->assertSame((string) $content->id, $event?->content_id);
        $this->assertSame((string) $content->creator_id, $event?->creator_id);
    }

    #[Test]
    public function a_first_observation_has_no_previous_status(): void
    {
        $content = $this->seedContent('session-2', ContentType::Vod, ContentStatus::Unknown);

        $event = app(VtualContentStatusRecorder::class)->record($content, ContentStatus::Ended);

        $this->assertNull($event?->previous_status);
        $this->assertSame(ContentStatus::Ended, $event?->status);
    }

    #[Test]
    public function a_repeat_of_the_same_status_writes_nothing(): void
    {
        $content = $this->seedContent('session-3', ContentType::Live, ContentStatus::Live);
        $recorder = app(VtualContentStatusRecorder::class);

        $this->assertNull($recorder->record($content, ContentStatus::Live, ContentStatus::Live));
        $this->assertSame(0, ContentStatusEvent::query()->count());
    }

    #[Test]
    public function a_six_hour_stream_leaves_two_rows_not_one_per_poll(): void
    {
        $content = $this->seedContent('session-4', ContentType::Vod, ContentStatus::Unknown);
        $recorder = app(VtualContentStatusRecorder::class);

        // 180 polls of a running stream, then the end.
        $recorder->record($content, ContentStatus::Live, ContentStatus::Unknown);
        for ($i = 0; $i < 179; $i++) {
            $recorder->record($content, ContentStatus::Live, ContentStatus::Live);
        }
        $recorder->record($content, ContentStatus::Ended, ContentStatus::Live);

        $this->assertSame(2, ContentStatusEvent::query()->count());
    }

    #[Test]
    public function the_timeline_is_disabled_with_the_history_switch(): void
    {
        $content = $this->seedContent('session-5', ContentType::Live, ContentStatus::Live);
        config(['vtual.metrics.enabled' => false]);

        $this->assertNull(app(VtualContentStatusRecorder::class)->record(
            $content,
            ContentStatus::Ended,
            ContentStatus::Live,
        ));
        $this->assertSame(0, ContentStatusEvent::query()->count());
    }

    #[Test]
    public function hydrate_records_the_transition_into_live(): void
    {
        app(ContentService::class)->upsertFromPrimary($this->channel, [
            'external_id' => 'session-6',
            'type' => 'live',
            'status' => 'live',
            'title' => 'Live now',
            'url' => 'https://example.test/session-6',
            'concurrent_viewers' => 300,
        ]);

        $event = ContentStatusEvent::query()->sole();
        $this->assertSame(ContentStatus::Live, $event->status);
        $this->assertNull($event->previous_status);
    }

    #[Test]
    public function a_rehydrate_of_the_same_state_adds_no_row(): void
    {
        $payload = [
            'external_id' => 'session-7',
            'type' => 'live',
            'status' => 'live',
            'title' => 'Live now',
            'url' => 'https://example.test/session-7',
            'concurrent_viewers' => 300,
        ];

        app(ContentService::class)->upsertFromPrimary($this->channel, $payload);
        app(ContentService::class)->upsertFromPrimary($this->channel, $payload + ['concurrent_viewers' => 900]);

        // Same session, same status: the CCV moved, the session did not.
        $this->assertSame(1, ContentStatusEvent::query()->count());
    }

    #[Test]
    public function ending_a_channel_records_the_transition_out_of_live(): void
    {
        app(ContentService::class)->upsertFromPrimary($this->channel, [
            'external_id' => 'session-8',
            'type' => 'live',
            'status' => 'live',
            'title' => 'Live now',
            'url' => 'https://example.test/session-8',
        ]);

        app(ContentService::class)->endLiveStreamsForChannel($this->channel);

        $statuses = ContentStatusEvent::query()->orderBy('occurred_at')->pluck('status')->all();
        $this->assertSame([ContentStatus::Live, ContentStatus::Ended], $statuses);
    }

    #[Test]
    public function ending_a_channel_twice_does_not_duplicate_the_end_event(): void
    {
        app(ContentService::class)->upsertFromPrimary($this->channel, [
            'external_id' => 'session-9',
            'type' => 'live',
            'status' => 'live',
            'title' => 'Live now',
            'url' => 'https://example.test/session-9',
        ]);

        app(ContentService::class)->endLiveStreamsForChannel($this->channel);
        app(ContentService::class)->endLiveStreamsForChannel($this->channel);

        $this->assertSame(1, ContentStatusEvent::query()
            ->where('status', ContentStatus::Ended->value)
            ->count());
    }

    #[Test]
    public function a_restream_is_a_different_content_row_and_therefore_a_different_session(): void
    {
        foreach (['session-a', 'session-b'] as $externalId) {
            app(ContentService::class)->upsertFromPrimary($this->channel, [
                'external_id' => $externalId,
                'type' => 'live',
                'status' => 'live',
                'title' => 'Live',
                'url' => 'https://example.test/'.$externalId,
            ]);
        }

        // No session id exists to correlate them: the platform's session id is the
        // external_id, so two sessions are two rows with two timelines.
        $this->assertSame(2, Content::query()->count());
        $this->assertSame(2, ContentStatusEvent::query()->distinct()->count('content_id'));
    }

    #[Test]
    public function a_tombstone_records_unavailable_once(): void
    {
        app(ContentService::class)->upsertFromPrimary($this->channel, [
            'external_id' => 'session-10',
            'type' => 'vod',
            'status' => 'ended',
            'title' => 'A VOD',
            'url' => 'https://example.test/session-10',
        ]);

        app(ContentService::class)->markUnavailable($this->channel, 'session-10');
        app(ContentService::class)->markUnavailable($this->channel, 'session-10');

        $this->assertSame(1, ContentStatusEvent::query()
            ->where('status', ContentStatus::Unavailable->value)
            ->count());
    }

    #[Test]
    public function latest_status_reads_the_newest_row(): void
    {
        $content = $this->seedContent('session-11', ContentType::Vod, ContentStatus::Unknown);
        $recorder = app(VtualContentStatusRecorder::class);
        $recorder->record($content, ContentStatus::Ended, ContentStatus::Unknown, now()->subHour());
        $recorder->record($content, ContentStatus::Unavailable, ContentStatus::Ended, now());

        $this->assertSame(ContentStatus::Unavailable, $recorder->latestStatus((string) $content->id));
    }

    #[Test]
    public function latest_status_is_null_without_history(): void
    {
        $content = $this->seedContent('session-12', ContentType::Vod, ContentStatus::Unknown);

        $this->assertNull(app(VtualContentStatusRecorder::class)->latestStatus((string) $content->id));
    }

    private function seedChannel(): Channel
    {
        $creator = Creator::query()->create([
            'slug' => 'status-'.Str::lower(Str::random(8)),
            'display_name' => 'Status Owner',
            'status' => EntityStatus::Active,
        ]);

        return Channel::query()->create([
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UCSTATUS'.Str::upper(Str::random(6)),
            'handle' => 'status-'.Str::lower(Str::random(6)),
            'display_name' => 'Status Channel',
            'status' => ChannelStatus::Active,
        ]);
    }

    private function seedContent(string $externalId, ContentType $type, ContentStatus $status): Content
    {
        $content = new Content([
            'creator_id' => $this->channel->creator_id,
            'channel_id' => $this->channel->id,
            'platform_id' => $this->channel->platform_id,
            'external_id' => $externalId,
            'type' => $type,
            'status' => $status,
            'title' => 'Status sample',
            'url' => 'https://example.test/'.$externalId,
        ]);
        $content->id = (string) Str::uuid7();
        $content->save();

        return $content;
    }
}
