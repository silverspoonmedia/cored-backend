<?php

namespace Tests\Domain\Vtual\Feature\Console\Commands;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ChannelVisibility;
use App\Domain\Vtual\Enums\ContentKind;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BindsAiConnection;
use Tests\TestCase;

class ClassifyContentsCommandTest extends TestCase
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

    #[Test]
    public function ai_audit_fills_null_sub_category_on_music_rows_only(): void
    {
        $music = $this->createContent('vid-music', '星を編む', ContentKind::Music);
        $nonMusic = $this->createContent('vid-game', 'Game play stream', ContentKind::NonMusic);

        // Endpoint + key now live in the core Ai domain (Tier C); only the
        // per-consumer knobs stay Tier B.
        $this->bindAiConnection(apiKey: 'test-key');

        config([
            'vtual.content_classification.ai.model' => 'audit-model',
            'vtual.content_classification.ai.enabled' => true,
        ]);

        Http::fake([
            'ai.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'category' => 'original',
                        'detail' => null,
                        'confidence' => 0.85,
                    ])]],
                ],
            ], 200),
        ]);

        $this->artisan('vtual:classify-contents', ['--ai' => true])
            ->assertSuccessful();

        $music->refresh();
        $nonMusic->refresh();

        $this->assertSame('original', $music->content_category?->value);
        $this->assertNull($nonMusic->content_category);

        // Only the music row reached the AI endpoint.
        Http::assertSentCount(1);
    }

    #[Test]
    public function ai_audit_fails_when_not_configured(): void
    {
        $this->createContent('vid-music', '星を編む', ContentKind::Music);

        // Knobs on, but no AI connection bound → the pass must refuse to run.
        $this->bindNoAiConnection();
        config([
            'vtual.content_classification.ai.model' => 'audit-model',
            'vtual.content_classification.ai.enabled' => true,
        ]);

        $this->artisan('vtual:classify-contents', ['--ai' => true])
            ->assertFailed();

        Http::assertNothingSent();
    }

    #[Test]
    public function heuristic_pass_runs_without_ai_option(): void
    {
        // "歌ってみた" is a strong cover keyword — heuristic pass classifies it.
        $cover = $this->createContent('vid-cover', 'ヴィラン 歌ってみた', ContentKind::Music);

        $this->artisan('vtual:classify-contents')
            ->assertSuccessful();

        $cover->refresh();
        $this->assertSame('cover', $cover->content_category?->value);

        Http::assertNothingSent();
    }

    private function createContent(string $externalId, string $title, ContentKind $kind): Content
    {
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
            'hydrated_at' => now(),
            'published_at' => now()->subDay(),
        ]);
    }
}
