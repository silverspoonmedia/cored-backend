<?php

namespace Tests\Domain\Vtual\Unit\Integrations\Music;

use App\Domain\Vtual\Integrations\Music\ItunesSearchClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The sub-category layer trusts this client for two very different jobs:
 * naming the original artist on a cover, and deciding "original" when the
 * catalog artist is the uploader. Both depend on the candidate ranking, so the
 * ranking rules (title overlap, cover-release exclusion, artist hint) are
 * pinned here.
 */
class ItunesSearchClientTest extends TestCase
{
    use RefreshDatabase;

    private ItunesSearchClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = app(ItunesSearchClient::class);
    }

    #[Test]
    public function ranks_candidates_by_track_title_overlap(): void
    {
        Http::fake([
            'itunes.apple.com/search*' => Http::response([
                'results' => [
                    // Loose match: barely half of the track title is in the query.
                    ['trackName' => 'モニタリング (KAFU Cover) Extra', 'artistName' => 'Noise'],
                    ['trackName' => 'モニタリング', 'artistName' => 'DECO*27', 'releaseDate' => '2024-11-23T12:00:00Z'],
                    ['trackName' => 'Unrelated Song', 'artistName' => 'Someone'],
                ],
            ], 200),
        ]);

        $candidates = $this->client->search('モニタリング');

        $this->assertCount(1, $candidates);
        $this->assertSame('DECO*27 - モニタリング', $candidates[0]['label']);
        $this->assertSame('2024-11-23T12:00:00Z', $candidates[0]['released']);
    }

    #[Test]
    public function drops_results_whose_title_marks_them_as_a_cover_release(): void
    {
        // A vTuber's own cover single must never satisfy the "uploader released
        // this song" test, so cover-marked titles are not candidates at all.
        Http::fake([
            'itunes.apple.com/search*' => Http::response([
                'results' => [
                    ['trackName' => 'KING (cover)', 'artistName' => 'Kanaria'],
                    ['trackName' => 'KING (self cover)', 'artistName' => 'Kanaria'],
                    ['trackName' => 'KING', 'artistName' => 'Kanaria', 'releaseDate' => '2020-08-02T07:00:00Z'],
                ],
            ], 200),
        ]);

        $candidates = $this->client->search('KING');

        $this->assertCount(1, $candidates);
        $this->assertSame('KING', $candidates[0]['track']);
    }

    #[Test]
    public function the_artist_hint_wins_over_a_higher_ranked_cover_single(): void
    {
        Http::fake([
            'itunes.apple.com/search*' => Http::response([
                'results' => [
                    ['trackName' => 'KING', 'artistName' => '麻婆豆腐'],
                    ['trackName' => 'KING', 'artistName' => 'Kanaria'],
                ],
            ], 200),
        ]);

        $track = $this->client->findTrackData('KING', 'Kanaria');

        $this->assertNotNull($track);
        $this->assertSame('Kanaria - KING', $track['label']);
    }

    #[Test]
    public function without_a_hint_the_best_title_match_is_returned(): void
    {
        Http::fake([
            'itunes.apple.com/search*' => Http::response([
                'results' => [
                    ['trackName' => 'KING', 'artistName' => '麻婆豆腐'],
                    ['trackName' => 'KING', 'artistName' => 'Kanaria'],
                ],
            ], 200),
        ]);

        $this->assertSame('麻婆豆腐 - KING', $this->client->findTrack('KING'));
    }

    #[Test]
    public function candidates_are_cached_per_term(): void
    {
        Cache::flush();

        Http::fake([
            'itunes.apple.com/search*' => Http::response([
                'results' => [['trackName' => 'ヴィラン', 'artistName' => 'てにをは']],
            ], 200),
        ]);

        $this->client->findTrackData('ヴィラン', 'Kanaria');
        $this->client->findTrackData('ヴィラン', 'Someone Else');

        Http::assertSentCount(1);
    }

    #[Test]
    public function a_failed_request_yields_no_candidates(): void
    {
        Http::fake(['itunes.apple.com/search*' => Http::response([], 500)]);

        $this->assertSame([], $this->client->search('ヴィラン'));
        $this->assertNull($this->client->findTrack('ヴィラン'));
    }

    #[Test]
    public function the_lookup_can_be_disabled(): void
    {
        config()->set('vtual.content_classification.itunes.enabled', false);
        Http::fake();

        $this->assertSame([], $this->client->search('ヴィラン'));
        Http::assertNothingSent();
    }

    #[Test]
    public function an_empty_term_is_not_searched(): void
    {
        Http::fake();

        $this->assertSame([], $this->client->search('   '));
        $this->assertNull($this->client->findTrack(''));
        Http::assertNothingSent();
    }

    #[Test]
    public function blank_artist_names_still_produce_a_label(): void
    {
        Http::fake([
            'itunes.apple.com/search*' => Http::response([
                'results' => [['trackName' => 'ヴィラン']],
            ], 200),
        ]);

        $this->assertSame('ヴィラン', $this->client->findTrack('ヴィラン'));
    }

    #[Test]
    public function descriptions_can_be_checked_for_an_artist_credit(): void
    {
        $description = 'lyrics & music：内緒のピアス / illustration: ふわり';

        $this->assertTrue($this->client->creditMentions($description, '内緒のピアス'));
        // Normalized comparison ignores spacing and punctuation.
        $this->assertTrue($this->client->creditMentions($description, '内緒の ピアス'));
        $this->assertFalse($this->client->creditMentions($description, '9Lana'));
        $this->assertFalse($this->client->creditMentions($description, ''));
    }
}
