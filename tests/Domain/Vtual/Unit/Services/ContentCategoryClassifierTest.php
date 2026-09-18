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

    // ---------------------------------------------------------------------
    // Regressions from the reported probe corpus.
    // ---------------------------------------------------------------------

    #[Test]
    public function feat_credit_alone_no_longer_makes_an_original_a_cover(): void
    {
        // Real case: 8BJ0ST-lj4Y. "feat. 初音ミク" plus the words 歌おう / この歌は
        // in the lyrics used to fire the weak-cover path (catId 10 + 238 s).
        Http::fake();

        $verdict = $this->classifier->evaluate([
            'title' => 'ジャバウォック / wotaku feat. 初音ミク',
            'description' => 'この歌は終わらないから 森の動物と歌おう',
            'category_id' => '10',
            'duration_seconds' => 238,
            'type' => 'vod',
            'channel_title' => 'wotaku',
        ]);

        $this->assertNull($verdict['category']);
        $this->assertSame('none', $verdict['signal']);
    }

    #[Test]
    public function remix_with_feat_credit_no_longer_makes_an_original_a_cover(): void
    {
        // Real case: C-CYwNz3z8w — an official remix credited "feat. 初音ミク".
        Http::fake();

        $verdict = $this->classifier->evaluate([
            'title' => 'DECO*27 - モニタリング (Best Friend Remix) feat. 初音ミク',
            'description' => 'Original MV DECO*27 - モニタリング feat. 初音ミク',
            'category_id' => '10',
            'duration_seconds' => 183,
            'type' => 'vod',
            'channel_title' => 'DECO*27',
        ]);

        $this->assertNull($verdict['category']);
    }

    #[Test]
    public function composer_provide_credit_marks_original(): void
    {
        // Real case: 8BJ0ST-lj4Y — the uploader wrote the song for another singer.
        Http::fake();

        $verdict = $this->classifier->evaluate([
            'title' => 'ジャバウォック / wotaku feat. 初音ミク',
            'description' => '兎迷夢々さんに提供した楽曲です！',
            'category_id' => '10',
            'duration_seconds' => 238,
            'type' => 'vod',
            'channel_title' => 'wotaku',
        ]);

        $this->assertSame(ContentCategory::Original, $verdict['category']);
        $this->assertSame('original_provide', $verdict['signal']);
    }

    #[Test]
    public function utattemita_label_wins_over_a_provide_credit_in_the_description(): void
    {
        // A cover whose description mentions the composer's 提供曲 must keep its
        // 歌ってみた verdict — the title label is the explicit one.
        Http::fake();

        $verdict = $this->classifier->evaluate([
            'title' => '【歌ってみた】星を編む',
            'description' => '作詞作曲：〇〇様の提供曲です https://youtu.be/abc',
            'category_id' => '10',
            'duration_seconds' => 200,
            'type' => 'vod',
        ]);

        $this->assertSame(ContentCategory::Cover, $verdict['category']);
        $this->assertSame('strong_cover', $verdict['signal']);
    }

    #[Test]
    public function cover_art_credit_does_not_make_a_video_a_cover(): void
    {
        // An original MV credits its illustrator; that is not a cover label.
        // Without the decoy strip, the weak "cover" keyword + catId 10 fired.
        Http::fake();

        $verdict = $this->classifier->evaluate([
            'title' => '星を編む',
            'description' => 'Cover Art: のう / Illustration: のう',
            'category_id' => '10',
            'duration_seconds' => 200,
            'type' => 'vod',
        ]);

        $this->assertNull($verdict['category']);
        Http::assertNothingSent();
    }

    #[Test]
    public function provenance_block_with_a_link_marks_cover(): void
    {
        // Real case: XPLkkdMFmco — "本家様:https://youtu.be/…".
        Http::fake([
            'itunes.apple.com/search*' => Http::response([
                'results' => [
                    ['trackName' => 'プロポーズ', 'artistName' => '内緒のピアス'],
                ],
            ], 200),
        ]);

        $verdict = $this->classifier->evaluate([
            'title' => '【MV】プロポーズ/ 9Lana cover',
            'description' => "Lyrics & Music：内緒のピアス\n本家様:https://youtu.be/ST_AJmY_Tcc",
            'category_id' => '10',
            'duration_seconds' => 234,
            'type' => 'vod',
            'channel_title' => '9Lana',
        ]);

        $this->assertSame(ContentCategory::Cover, $verdict['category']);
        $this->assertSame('cover_provenance', $verdict['signal']);
        $this->assertSame('内緒のピアス - プロポーズ', $verdict['detail']);
    }

    #[Test]
    public function attribution_prefers_the_artist_the_description_credits(): void
    {
        // The coverer's own streaming release is in the catalog too, and it sorts
        // first (its artist matches the uploader). Reporting it as "the original"
        // would be wrong, so a candidate credited in the description wins.
        Http::fake([
            'itunes.apple.com/search*' => Http::response([
                'results' => [
                    ['trackName' => 'プロポーズ', 'artistName' => '9Lana', 'releaseDate' => '2024-08-24T12:00:00Z'],
                    ['trackName' => 'プロポーズ', 'artistName' => '内緒のピアス', 'releaseDate' => '2023-08-16T12:00:00Z'],
                ],
            ], 200),
        ]);

        $verdict = $this->classifier->evaluate([
            'title' => '【MV】プロポーズ/ 9Lana cover',
            'description' => "Lyrics & Music：内緒のピアス\n本家様:https://youtu.be/ST_AJmY_Tcc",
            'category_id' => '10',
            'duration_seconds' => 234,
            'type' => 'vod',
            'channel_title' => '9Lana',
        ]);

        $this->assertSame(ContentCategory::Cover, $verdict['category']);
        $this->assertSame('内緒のピアス - プロポーズ', $verdict['detail']);
    }

    #[Test]
    public function the_word_original_with_a_link_never_marks_cover(): void
    {
        // The music layer's original_credit_markers include the English word
        // "original"; reusing that list here would label every original MV a cover.
        Http::fake();

        $verdict = $this->classifier->evaluate([
            'title' => '星を編む',
            'description' => 'Original MV https://youtu.be/abc',
            'category_id' => '10',
            'duration_seconds' => 200,
            'type' => 'vod',
        ]);

        $this->assertNull($verdict['category']);
    }

    #[Test]
    public function uploader_tags_label_the_sub_category(): void
    {
        Http::fake();

        $cover = $this->classifier->evaluate([
            'title' => '君の知らない物語',
            'description' => null,
            'category_id' => '24',
            'duration_seconds' => 338,
            'type' => 'archive',
            'tags' => ['Vtuber', '歌ってみた', 'supercell'],
        ]);
        $this->assertSame(ContentCategory::Cover, $cover['category']);
        $this->assertSame('tags', $cover['signal']);

        $original = $this->classifier->evaluate([
            'title' => '星を編む',
            'description' => null,
            'category_id' => '10',
            'duration_seconds' => 200,
            'type' => 'vod',
            'tags' => ['オリジナル曲', 'cover'],
        ]);
        $this->assertSame(ContentCategory::Original, $original['category']);
    }

    #[Test]
    public function catalog_artist_matching_the_uploader_marks_original(): void
    {
        // Real case: cm-l2h6GB8Q — "【GUMI】KING【Kanaria】" carries no keyword at
        // all, but the catalog says the uploader released the song.
        Http::fake([
            'itunes.apple.com/search*' => Http::response([
                'results' => [
                    ['trackName' => 'KING', 'artistName' => '麻婆豆腐', 'releaseDate' => '2021-09-01T12:00:00Z'],
                    ['trackName' => 'KING', 'artistName' => 'Kanaria', 'releaseDate' => '2020-08-02T07:00:00Z'],
                ],
            ], 200),
        ]);

        $verdict = $this->classifier->evaluate([
            'title' => '【GUMI】KING【Kanaria】',
            'description' => null,
            'category_id' => '10',
            'duration_seconds' => 135,
            'type' => 'vod',
            'channel_title' => 'Kanaria',
        ]);

        $this->assertSame(ContentCategory::Original, $verdict['category']);
        $this->assertSame('catalog_artist_match', $verdict['signal']);
        $this->assertSame('Kanaria - KING', $verdict['catalog']);
    }

    #[Test]
    public function catalog_artist_differing_from_the_uploader_marks_cover(): void
    {
        Http::fake([
            'itunes.apple.com/search*' => Http::response([
                'results' => [
                    ['trackName' => 'KING', 'artistName' => 'Someone Else'],
                ],
            ], 200),
        ]);

        $verdict = $this->classifier->evaluate([
            'title' => '【GUMI】KING【Kanaria】',
            'description' => null,
            'category_id' => '10',
            'duration_seconds' => 135,
            'type' => 'vod',
            'channel_title' => 'Kanaria',
        ]);

        $this->assertSame(ContentCategory::Cover, $verdict['category']);
        $this->assertSame('catalog_artist_mismatch', $verdict['signal']);
        $this->assertSame('Someone Else - KING', $verdict['detail']);
    }

    #[Test]
    public function catalog_check_is_skipped_without_a_channel_title(): void
    {
        Http::fake();

        $verdict = $this->classifier->evaluate([
            'title' => '【GUMI】KING【Kanaria】',
            'description' => null,
            'category_id' => '10',
            'duration_seconds' => 135,
            'type' => 'vod',
        ]);

        $this->assertNull($verdict['category']);
        Http::assertNothingSent();
    }

    #[Test]
    public function catalog_check_can_be_disabled_by_config(): void
    {
        config()->set('vtual.content_classification.itunes.artist_disambiguation', false);
        Http::fake();

        $verdict = $this->classifier->evaluate([
            'title' => '【GUMI】KING【Kanaria】',
            'description' => null,
            'category_id' => '10',
            'duration_seconds' => 135,
            'type' => 'vod',
            'channel_title' => 'Kanaria',
        ]);

        $this->assertNull($verdict['category']);
        Http::assertNothingSent();
    }

    #[Test]
    public function the_catalog_query_drops_the_cover_label(): void
    {
        // Leaving "cover" in the term biases iTunes toward cover singles.
        Http::fake([
            'itunes.apple.com/search*' => Http::response(['results' => []], 200),
        ]);

        $this->classifier->evaluate([
            'title' => 'magnet - くろくも cover',
            'description' => null,
            'category_id' => '10',
            'duration_seconds' => 242,
            'type' => 'vod',
            'channel_title' => 'Kurokumo Official',
        ]);

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['term'] ?? null) === 'magnet - くろくも';
        });
    }

    #[Test]
    public function every_branch_reports_its_signal(): void
    {
        $cases = [
            ['title' => 'オリジナル曲', 'signal' => 'original_keyword'],
            ['title' => '【歌枠】ゆるりと', 'signal' => 'singing_stream'],
            ['title' => 'ヴィラン 歌ってみた', 'signal' => 'strong_cover'],
            ['title' => 'KING ( COVER )', 'signal' => 'strong_cover'],
        ];

        foreach ($cases as $case) {
            $verdict = $this->classifier->evaluate([
                'title' => $case['title'],
                'description' => null,
                'category_id' => '10',
                'duration_seconds' => 200,
                'type' => 'vod',
            ]);

            $this->assertSame($case['signal'], $verdict['signal'], $case['title']);
        }
    }

    #[Test]
    public function channel_and_catalog_artist_names_are_compared_loosely(): void
    {
        $client = app(ItunesSearchClient::class);

        $this->assertTrue($client->artistMatchesChannel('Kanaria', 'Kanaria'));
        $this->assertTrue($client->artistMatchesChannel('KANARIA', 'Kanaria'));
        $this->assertTrue($client->artistMatchesChannel('Kanaria', 'Kanaria - Topic'));
        $this->assertTrue($client->artistMatchesChannel('DECO*27', 'DECO*27'));
        $this->assertTrue($client->artistMatchesChannel('獅子神レオナ', '獅子神レオナ/レオナちゃんねる'));
        $this->assertFalse($client->artistMatchesChannel('Kanaria', 'wotaku'));
        // Containment below the floor length must not match.
        $this->assertFalse($client->artistMatchesChannel('ai', 'Kairi'));
        $this->assertFalse($client->artistMatchesChannel('', 'Kanaria'));
    }
}
