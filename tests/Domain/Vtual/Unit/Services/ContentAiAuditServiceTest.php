<?php

namespace Tests\Domain\Vtual\Unit\Services;

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
use App\Domain\Vtual\Services\ContentAiAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BindsAiConnection;
use Tests\TestCase;

/**
 * The enqueue half of the AI audit. The pool is only useful if it captures
 * exactly the rows the program classifier could not finish â€” and nothing else.
 */
class ContentAiAuditServiceTest extends TestCase
{
    use BindsAiConnection;
    use RefreshDatabase;

    private Platform $youtube;

    protected function setUp(): void
    {
        parent::setUp();

        $this->youtube = Platform::query()->create([
            'key' => 'youtube',
            'name' => 'YouTube',
        ]);
    }

    /**
     * Resolved per call: the resolver instance is swapped by bindAiConnection(),
     * so the service must be built after that swap to see it.
     */
    private function service(): ContentAiAuditService
    {
        return app(ContentAiAuditService::class);
    }

    #[Test]
    public function enqueues_a_row_whose_music_layer_is_unknown(): void
    {
        $this->bindAiConnection();
        $this->enableAudit();

        $content = $this->content('vid-unknown', kind: null);

        $this->service()->enqueueIfEligible($content);

        $this->assertSame(1, ContentAiAuditItem::query()->count());
        $item = ContentAiAuditItem::query()->firstOrFail();
        $this->assertSame($content->id, $item->content_id);
        $this->assertSame(ContentAiAuditStatus::Pending, $item->status);
        $this->assertSame(0, $item->attempts);
        $this->assertNotNull($item->requested_at);
    }

    #[Test]
    public function enqueues_music_without_a_sub_category(): void
    {
        $this->bindAiConnection();
        $this->enableAudit();

        $this->service()->enqueueIfEligible($this->content('vid-music', kind: ContentKind::Music));

        $this->assertSame(1, ContentAiAuditItem::query()->count());
    }

    #[Test]
    public function skips_a_fully_resolved_row(): void
    {
        $this->bindAiConnection();
        $this->enableAudit();

        $this->service()->enqueueIfEligible($this->content(
            'vid-resolved',
            kind: ContentKind::Music,
            category: ContentCategory::Cover,
        ));

        $this->assertSame(0, ContentAiAuditItem::query()->count());
    }

    #[Test]
    public function skips_a_non_music_row(): void
    {
        $this->bindAiConnection();
        $this->enableAudit();

        $this->service()->enqueueIfEligible($this->content('vid-game', kind: ContentKind::NonMusic));

        $this->assertSame(0, ContentAiAuditItem::query()->count());
    }

    #[Test]
    public function is_a_no_op_when_the_audit_is_disabled_or_unbound(): void
    {
        $this->bindAiConnection();
        $this->enableAudit(enabled: false);

        $this->service()->enqueueIfEligible($this->content('vid-disabled', kind: null));

        $this->bindNoAiConnection();
        $this->enableAudit();
        $this->service()->enqueueIfEligible($this->content('vid-unbound', kind: null));

        $this->assertSame(0, ContentAiAuditItem::query()->count());
    }

    #[Test]
    public function does_not_duplicate_an_existing_pending_row(): void
    {
        $this->bindAiConnection();
        $this->enableAudit();

        $content = $this->content('vid-dupe', kind: null);

        $this->service()->enqueueIfEligible($content);
        $this->service()->enqueueIfEligible($content);
        $this->service()->enqueueIfEligible($content);

        $this->assertSame(1, ContentAiAuditItem::query()->count());
    }

    private function enableAudit(bool $enabled = true): void
    {
        config([
            'vtual.content_classification.ai.enabled' => $enabled,
            'vtual.content_classification.ai.model' => 'audit-model',
        ]);
    }

    private function content(
        string $externalId,
        ?ContentKind $kind,
        ?ContentCategory $category = null,
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
            'title' => 'æ˜Ÿã‚’ç·¨ã‚€',
            'content_type' => $kind,
            'content_category' => $category,
            'hydrated_at' => now(),
            'published_at' => now()->subDay(),
        ]);
    }
}
