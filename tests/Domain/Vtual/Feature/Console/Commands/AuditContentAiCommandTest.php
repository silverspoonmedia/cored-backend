<?php

namespace Tests\Domain\Vtual\Feature\Console\Commands;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ChannelVisibility;
use App\Domain\Vtual\Enums\ContentAiAuditStatus;
use App\Domain\Vtual\Enums\ContentCategory;
use App\Domain\Vtual\Enums\ContentKind;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Models\ContentAiAuditItem;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BindsAiConnection;
use Tests\TestCase;

/**
 * Without this drainer the audit pool is write-only and the async AI pass never
 * runs — which is exactly the defect this command was added to fix.
 */
class AuditContentAiCommandTest extends TestCase
{
    use BindsAiConnection;
    use RefreshDatabase;

    private Platform $youtube;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->youtube = Platform::query()->create([
            'key' => 'youtube',
            'name' => 'YouTube',
        ]);
    }

    #[Test]
    public function refuses_to_run_without_a_configured_ai_connection(): void
    {
        $this->bindNoAiConnection();
        $this->enableAudit();
        $this->queue($this->content('vid-unbound', kind: null));

        $this->artisan('vtual:audit-content-ai')->assertFailed();

        Http::assertNothingSent();
        $this->assertSame(1, ContentAiAuditItem::query()->where('status', 'pending')->count());
    }

    #[Test]
    public function drains_a_pending_row_and_writes_both_layers(): void
    {
        $this->bindAiConnection();
        $this->enableAudit();

        $content = $this->content('vid-unknown', kind: null, title: '星を編む');
        $item = $this->queue($content);

        $this->fakeVerdict(['is_music' => true, 'category' => 'cover', 'detail' => 'Kanaria - KING', 'confidence' => 0.92]);

        $this->artisan('vtual:audit-content-ai')->assertSuccessful();

        $content->refresh();
        $item->refresh();

        $this->assertSame('music', $content->content_type?->value);
        $this->assertSame('cover', $content->content_category?->value);
        $this->assertSame('Kanaria - KING', $content->content_category_detail);
        $this->assertSame(ContentAiAuditStatus::Done, $item->status);
        $this->assertSame(1, $item->attempts);
        $this->assertNotNull($item->processed_at);
        $this->assertNull($item->error);
    }

    #[Test]
    public function a_not_music_verdict_clears_a_stale_sub_category(): void
    {
        $this->bindAiConnection();
        $this->enableAudit();

        $content = $this->content(
            'vid-talk',
            kind: null,
            category: ContentCategory::Cover,
            detail: 'stale attribution',
        );
        $item = $this->queue($content);

        $this->fakeVerdict(['is_music' => false, 'category' => null, 'detail' => null, 'confidence' => 0.95]);

        $this->artisan('vtual:audit-content-ai')->assertSuccessful();

        $content->refresh();

        $this->assertSame('non_music', $content->content_type?->value);
        $this->assertNull($content->content_category);
        $this->assertNull($content->content_category_detail);
        $this->assertSame(ContentAiAuditStatus::Done, $item->refresh()->status);
    }

    #[Test]
    public function a_verdict_without_a_sub_category_still_resolves_the_music_layer(): void
    {
        $this->bindAiConnection();
        $this->enableAudit();

        $content = $this->content('vid-music-nocat', kind: null);
        $item = $this->queue($content);

        $this->fakeVerdict(['is_music' => true, 'category' => null, 'detail' => null, 'confidence' => 0.8]);

        $this->artisan('vtual:audit-content-ai')->assertSuccessful();

        $content->refresh();

        $this->assertSame('music', $content->content_type?->value);
        $this->assertNull($content->content_category);
        $this->assertSame(ContentAiAuditStatus::Done, $item->refresh()->status);
    }

    #[Test]
    public function a_failed_call_marks_the_row_failed_and_leaves_the_classification_alone(): void
    {
        $this->bindAiConnection();
        $this->enableAudit();

        $content = $this->content('vid-fail', kind: null);
        $item = $this->queue($content);

        Http::fake(['ai.test/*' => Http::response([], 500)]);

        $this->artisan('vtual:audit-content-ai')->assertSuccessful();

        $content->refresh();
        $item->refresh();

        $this->assertNull($content->content_type);
        $this->assertSame(ContentAiAuditStatus::Failed, $item->status);
        $this->assertSame('no_verdict', $item->error);
        $this->assertSame(1, $item->attempts);
    }

    #[Test]
    public function respects_the_attempt_cap(): void
    {
        $this->bindAiConnection();
        $this->enableAudit();

        $content = $this->content('vid-capped', kind: null);
        $item = $this->queue($content);
        // Already burned the allowed attempts in earlier runs.
        $item->forceFill(['attempts' => 3])->save();

        Http::fake();

        $this->artisan('vtual:audit-content-ai', ['--max-attempts' => 3])->assertSuccessful();

        $this->assertSame(ContentAiAuditStatus::Pending, $item->refresh()->status);
        Http::assertNothingSent();
    }

    #[Test]
    public function requeues_rows_stuck_in_processing(): void
    {
        $this->bindAiConnection();
        $this->enableAudit();

        $content = $this->content('vid-stale', kind: null);
        $item = $this->queue($content, status: ContentAiAuditStatus::Processing);
        $item->forceFill(['updated_at' => now()->subMinutes(60)])->save();

        Http::fake();

        // Nothing to claim (it was requeued but the run already claimed), so this
        // asserts the requeue itself rather than a classification.
        $this->artisan('vtual:audit-content-ai', ['--stale-minutes' => 15])->assertSuccessful();

        // The stale row was returned to the pool, then claimed by this run.
        $item->refresh();
        $this->assertSame(1, $item->attempts);
        $this->assertNotSame(ContentAiAuditStatus::Processing, $item->status);
    }

    #[Test]
    public function a_soft_deleted_content_row_is_closed_out_rather_than_retried(): void
    {
        $this->bindAiConnection();
        $this->enableAudit();

        $content = $this->content('vid-gone', kind: null);
        $item = $this->queue($content);

        // Soft delete keeps the pool row (FK cascade only fires on a hard
        // delete) while Content::find() stops seeing the content.
        $content->delete();

        Http::fake();

        $this->artisan('vtual:audit-content-ai')->assertSuccessful();

        $this->assertSame(ContentAiAuditStatus::Done, $item->refresh()->status);
        Http::assertNothingSent();
    }

    #[Test]
    public function nothing_to_do_is_a_success(): void
    {
        $this->bindAiConnection();
        $this->enableAudit();

        Http::fake();

        $this->artisan('vtual:audit-content-ai')
            ->expectsOutputToContain('Nothing to audit.')
            ->assertSuccessful();
    }

    private function enableAudit(): void
    {
        config([
            'vtual.content_classification.ai.enabled' => true,
            'vtual.content_classification.ai.model' => 'audit-model',
            'vtual.content_classification.ai.min_confidence' => 0.7,
        ]);
    }

    /**
     * @param  array<string, mixed>  $verdict
     */
    private function fakeVerdict(array $verdict): void
    {
        Http::fake([
            'ai.test/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($verdict)]]],
            ], 200),
        ]);
    }

    private function queue(
        Content $content,
        ContentAiAuditStatus $status = ContentAiAuditStatus::Pending,
    ): ContentAiAuditItem {
        return ContentAiAuditItem::query()->create([
            'content_id' => $content->id,
            'status' => $status,
            'attempts' => 0,
            'requested_at' => now(),
        ]);
    }

    private function content(
        string $externalId,
        ?ContentKind $kind,
        ?ContentCategory $category = null,
        ?string $detail = null,
        string $title = '星を編む',
    ): Content {
        $creator = Creator::query()->create([
            'slug' => $externalId,
            'display_name' => 'Creator '.$externalId,
            'status' => EntityStatus::Active,
        ]);

        $channel = Channel::query()->create([
            'creator_id' => $creator->id,
            'platform_id' => $this->youtube->id,
            'external_id' => 'UC-'.$externalId,
            'display_name' => $creator->display_name,
            'url' => 'https://youtube.com/@'.$externalId,
            'status' => ChannelStatus::Active,
            'visibility' => ChannelVisibility::Public,
        ]);

        return Content::query()->create([
            'creator_id' => $creator->id,
            'channel_id' => $channel->id,
            'platform_id' => $this->youtube->id,
            'external_id' => $externalId,
            'type' => ContentType::Vod,
            'status' => ContentStatus::Uploaded,
            'title' => $title,
            'content_type' => $kind,
            'content_category' => $category,
            'content_category_detail' => $detail,
            'hydrated_at' => now(),
            'published_at' => now()->subDay(),
        ]);
    }
}
