<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Vtual\Enums\ContentCategory;
use App\Domain\Vtual\Integrations\Music\ItunesSearchClient;
use App\Domain\Vtual\Services\ContentCategoryClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentCategoryClassifierTest extends TestCase
{
    use RefreshDatabase;

    private ContentCategoryClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = app(ContentCategoryClassifier::class);
    }

    #[Test]
    public function utattemita_title_is_cover(): void
    {
        $result = $this->classifier->classify([
            'title' => '【ヴィラン】歌ってみた【歌ってみた】',
            'description' => null,
            'category_id' => '10',
            'duration_seconds' => 200,
            'type' => 'vod',
        ]);

        $this->assertNotNull($result);
        $this->assertSame(ContentCategory::Cover, $result['category']);
    }

    #[Test]
    public function covered_by_title_is_cover_without_itunes_call(): void
    {
        Http::fake();

        $result = $this->classifier->classify([
            'title' => 'ヴィラン / covered by ななし',
            'description' => null,
            'category_id' => null,
            'duration_seconds' => 180,
            'type' => 'vod',
        ]);

        $this->assertNotNull($result);
        $this->assertSame(ContentCategory::Cover, $result['category']);
        Http::assertNothingSent();
    }

    #[Test]
    public function original_keyword_beats_cover_keyword(): void
    {
        $result = $this->classifier->classify([
            'title' => 'オリジナル曲【】(not a cover)',
            'description' => 'cover, cover, cover',
            'category_id' => null,
            'duration_seconds' => 200,
            'type' => 'vod',
        ]);

        $this->assertNotNull($result);
        $this->assertSame(ContentCategory::Original, $result['category']);
    }

    #[Test]
    public function utawaku_live_is_singing_stream(): void
    {
        $result = $this->classifier->classify([
            'title' => '【歌枠】雑談メインでたまに歌います',
            'description' => null,
            'category_id' => '24',
            'duration_seconds' => null,
            'type' => 'archive',
        ]);

        $this->assertNotNull($result);
        $this->assertSame(ContentCategory::SingingStream, $result['category']);
    }

    #[Test]
    public function singing_stream_on_vod_without_strong_cover_signal_still_classifies(): void
    {
        $result = $this->classifier->classify([
            'title' => 'singing stream archive part 2',
            'description' => null,
            'category_id' => null,
            'duration_seconds' => 5400,
            'type' => 'vod',
        ]);

        $this->assertNotNull($result);
        $this->assertSame(ContentCategory::SingingStream, $result['category']);
    }

    #[Test]
    public function weak_cover_with_music_category_verifies_via_itunes(): void
    {
        Http::fake([
            'itunes.apple.com/search*' => Http::response([
                'results' => [
                    ['trackName' => 'ヴィラン', 'artistName' => 'てにをは'],
                    ['trackName' => 'Unrelated', 'artistName' => 'Someone'],
                ],
            ], 200),
        ]);

        $result = $this->classifier->classify([
            'title' => 'ヴィラン / cover',
            'description' => null,
            'category_id' => '10',
            'duration_seconds' => 200,
            'type' => 'vod',
        ]);

        $this->assertNotNull($result);
        $this->assertSame(ContentCategory::Cover, $result['category']);
        $this->assertSame('てにをは - ヴィラン', $result['detail']);
    }

    #[Test]
    public function weak_cover_outside_music_category_and_duration_is_null(): void
    {
        $result = $this->classifier->classify([
            'title' => 'I covered my desk with stickers (talk)',
            'description' => null,
            'category_id' => '24',
            'duration_seconds' => 1800,
            'type' => 'vod',
        ]);

        $this->assertNull($result);
    }

    #[Test]
    public function talk_video_without_signals_is_null(): void
    {
        $result = $this->classifier->classify([
            'title' => '【雑談】まったりおはよう',
            'description' => null,
            'category_id' => '24',
            'duration_seconds' => 7200,
            'type' => 'vod',
        ]);

        $this->assertNull($result);
    }

    #[Test]
    public function empty_title_is_null(): void
    {
        $this->assertNull($this->classifier->classify(['title' => null, 'type' => 'vod']));
    }

    #[Test]
    public function catalog_term_strips_decorations(): void
    {
        $this->assertSame(
            '歌ってみた / covered by ななし',
            $this->classifier->catalogTerm('【ヴィラン】歌ってみた / covered by ななし'),
        );
    }

    #[Test]
    public function itunes_failure_leaves_weak_cover_unverified_but_still_cover(): void
    {
        Http::fake([
            'itunes.apple.com/search*' => Http::response([], 500),
        ]);

        $result = $this->classifier->classify([
            'title' => 'some song cover by me',
            'description' => null,
            'category_id' => '10',
            'duration_seconds' => 200,
            'type' => 'vod',
        ]);

        $this->assertNotNull($result);
        $this->assertSame(ContentCategory::Cover, $result['category']);
        $this->assertNull($result['detail']);
    }

    #[Test]
    public function itunes_disabled_skips_lookup(): void
    {
        config()->set('vtual.content_classification.itunes.enabled', false);
        Http::fake();

        $client = app(ItunesSearchClient::class);

        $this->assertNull($client->findTrack('ヴィラン'));
        Http::assertNothingSent();
    }
}
