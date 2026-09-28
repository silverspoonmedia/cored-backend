<?php

namespace Tests\Domain\Vtual\Feature\Console\Commands;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ChannelVisibility;
use App\Domain\Vtual\Enums\ContentCategory;
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
                        'is_music' => true,
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

        // Only the incomplete music row reached the AI endpoint.
        Http::assertSentCount(1);
    }

    #[Test]
    public function ai_pass_resolves_the_music_layer_on_unknown_rows(): void
    {
        // The row the program pass cannot read: short form, no signals.
        $unknown = $this->createContent('vid-unknown', '星を編む', null, durationSeconds: 200);

        $this->bindAiConnection(apiKey: 'test-key');
        config([
            'vtual.content_classification.ai.model' => 'audit-model',
            'vtual.content_classification.ai.enabled' => true,
        ]);

        Http::fake([
            // The program pass must abstain here, or the row is resolved before
            // the AI is asked — and a program verdict binds (see
            // ContentAiVerdictApplier), so the AI verdict under test would be
            // refused. Unfaked, this iTunes lookup answers from the live
            // catalogue and resolves "星を編む" as a cover of a real track.
            'itunes.apple.com/search*' => Http::response(['results' => []], 200),
            'ai.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'is_music' => true,
                        'category' => 'cover',
                        'detail' => 'Kanaria - KING',
                        'confidence' => 0.9,
                    ])]],
                ],
            ], 200),
        ]);

        $this->artisan('vtual:classify-contents', ['--ai' => true])
            ->assertSuccessful();

        $unknown->refresh();

        // Both layers written, not just the sub-category.
        $this->assertSame('music', $unknown->content_type?->value);
        $this->assertSame('cover', $unknown->content_category?->value);
        $this->assertSame('Kanaria - KING', $unknown->content_category_detail);
    }

    #[Test]
    public function ai_pass_leaves_rows_the_program_already_resolved_alone(): void
    {
        // non_music with no sub-category is a complete verdict — not an audit
        // candidate. Only "unknown" and "music without a sub-category" are.
        $nonMusic = $this->createContent('vid-resolved', 'Game play stream', ContentKind::NonMusic);

        $this->bindAiConnection(apiKey: 'test-key');
        config([
            'vtual.content_classification.ai.model' => 'audit-model',
            'vtual.content_classification.ai.enabled' => true,
        ]);

        Http::fake(['ai.test/*' => Http::response([], 500)]);

        $this->artisan('vtual:classify-contents', ['--ai' => true])
            ->assertSuccessful();

        $nonMusic->refresh();

        $this->assertSame('non_music', $nonMusic->content_type?->value);
        Http::assertNothingSent();
    }

    #[Test]
    public function ai_pass_writes_the_music_layer_when_the_ai_says_not_music(): void
    {
        $unknown = $this->createContent('vid-talk', '雑談', null, durationSeconds: 300);

        $this->bindAiConnection(apiKey: 'test-key');
        config([
            'vtual.content_classification.ai.model' => 'audit-model',
            'vtual.content_classification.ai.enabled' => true,
        ]);

        Http::fake([
            'ai.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'is_music' => false,
                        'category' => null,
                        'detail' => null,
                        'confidence' => 0.92,
                    ])]],
                ],
            ], 200),
        ]);

        $this->artisan('vtual:classify-contents', ['--ai' => true])
            ->assertSuccessful();

        $unknown->refresh();

        $this->assertSame('non_music', $unknown->content_type?->value);
        $this->assertNull($unknown->content_category);
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

    #[Test]
    public function refresh_recomputes_a_verdict_the_current_rules_no_longer_produce(): void
    {
        // The title carries only "feat.", which the previous weak-cover
        // vocabulary treated as a cover label.
        $row = $this->createContent('vid-remix', 'DECO*27 - モニタリング (Best Friend Remix) feat. 初音ミク', ContentKind::Music);
        $row->forceFill([
            'content_category' => ContentCategory::Cover,
            'content_category_detail' => 'DECO*27 - モニタリング',
        ])->save();

        // An ordinary run never overwrites an existing label…
        $this->artisan('vtual:classify-contents')->assertSuccessful();
        $this->assertSame('cover', $row->refresh()->content_category?->value);

        // …so a classifier change needs --refresh to repair stored rows. With no
        // other signal the row goes back to the AI-audit bucket instead of
        // keeping a label nothing can justify any more.
        $this->artisan('vtual:classify-contents', ['--refresh' => true])->assertSuccessful();

        $row->refresh();
        $this->assertNull($row->content_category);
        $this->assertNull($row->content_category_detail);
        $this->assertSame('music', $row->content_type?->value);

        Http::assertNothingSent();
    }

    #[Test]
    public function refresh_runs_the_catalog_check_with_the_channel_name(): void
    {
        $row = $this->createContent('vid-king', '【GUMI】KING【Kanaria】', ContentKind::Music, durationSeconds: 135);
        $row->channel->forceFill(['display_name' => 'Kanaria'])->save();

        Http::fake([
            'itunes.apple.com/search*' => Http::response([
                'results' => [['trackName' => 'KING', 'artistName' => 'Kanaria']],
            ], 200),
        ]);

        $this->artisan('vtual:classify-contents', ['--refresh' => true])->assertSuccessful();

        $row->refresh();
        $this->assertSame('original', $row->content_category?->value);
    }

    private function createContent(
        string $externalId,
        string $title,
        ?ContentKind $kind,
        ?int $durationSeconds = null,
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

        // The stored row carries no categoryId / topics / tags (those are
        // transient mapper inputs), so duration is the only extra signal a
        // backfill can offer. Model it as a live window.
        $startedAt = $durationSeconds !== null ? now()->subSeconds($durationSeconds) : null;

        return Content::query()->create([
            'creator_id' => $creator->id,
            'channel_id' => $channel->id,
            'platform_id' => $this->youtube->id,
            'external_id' => $externalId,
            'type' => ContentType::Vod,
            'status' => ContentStatus::Uploaded,
            'title' => $title,
            'content_type' => $kind,
            'started_at' => $startedAt,
            'ended_at' => $startedAt !== null ? now() : null,
            'hydrated_at' => now(),
            'published_at' => now()->subDay(),
        ]);
    }
}
