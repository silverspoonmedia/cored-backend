<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Vtual\Enums\ContentKind;
use App\Domain\Vtual\Services\ContentKindClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fixtures are the three real ids from the live probe that motivated this
 * layer: all three are music, but only one is filed under categoryId 10.
 */
class ContentKindClassifierTest extends TestCase
{
    use RefreshDatabase;

    private ContentKindClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = app(ContentKindClassifier::class);
    }

    #[Test]
    public function music_topic_wins_on_the_original_upload(): void
    {
        // cm-l2h6GB8Q 【GUMI】KING【Kanaria】 — categoryId 10.
        $result = $this->classifier->classify([
            'title' => '【GUMI】KING【Kanaria】',
            'description' => 'Music / Kanaria',
            'category_id' => '10',
            'duration_seconds' => 135,
            'topic_categories' => ['https://en.wikipedia.org/wiki/Music'],
            'tags' => ['GUMI', 'VOCALOID'],
        ]);

        $this->assertSame(ContentKind::Music, $result['kind']);
        $this->assertSame('topic', $result['signal']);
    }

    #[Test]
    public function music_topic_beats_a_non_music_category_on_a_cover(): void
    {
        // FdTy0ZWKYRo KING ( COVER ) / 橘ひなの — filed under 22 (People & Blogs).
        $cover = $this->classifier->classify([
            'title' => 'KING ( COVER ) / 橘ひなの',
            'description' => '本家様：https://youtu.be/cm-l2h6GB8Q',
            'category_id' => '22',
            'duration_seconds' => 135,
            'topic_categories' => ['https://en.wikipedia.org/wiki/Music'],
            'tags' => [],
        ]);

        $this->assertSame(ContentKind::Music, $cover['kind']);
        $this->assertSame('topic', $cover['signal']);

        // qNIhngowViI KING / Gawr Gura x Calliope Mori (Cover) — filed under 20 (Gaming).
        $hololive = $this->classifier->classify([
            'title' => 'KING / Gawr Gura x Calliope Mori (Cover)',
            'description' => 'original: Kanaria https://www.youtube.com/watch?v=cm-l2h6GB8Q',
            'category_id' => '20',
            'duration_seconds' => 143,
            'topic_categories' => ['https://en.wikipedia.org/wiki/Music'],
            'tags' => ['hololive'],
        ]);

        $this->assertSame(ContentKind::Music, $hololive['kind']);
        $this->assertSame('topic', $hololive['signal']);
    }

    #[Test]
    public function music_category_id_is_music_without_a_topic(): void
    {
        $result = $this->classifier->classify([
            'title' => '星を編む',
            'category_id' => '10',
            'duration_seconds' => 240,
            'topic_categories' => [],
            'tags' => [],
        ]);

        $this->assertSame(ContentKind::Music, $result['kind']);
        $this->assertSame('category_id', $result['signal']);
    }

    #[Test]
    public function a_music_keyword_in_the_title_is_music(): void
    {
        $result = $this->classifier->classify([
            'title' => '【歌ってみた】烏 - 米津玄師',
            'description' => null,
            'category_id' => '22',
            'duration_seconds' => 255,
            'topic_categories' => [],
            'tags' => [],
        ]);

        $this->assertSame(ContentKind::Music, $result['kind']);
        $this->assertSame('keyword', $result['signal']);
    }

    #[Test]
    public function a_music_keyword_in_the_uploader_tags_is_music(): void
    {
        $result = $this->classifier->classify([
            'title' => '星を編む',
            'description' => null,
            'category_id' => '24',
            'duration_seconds' => 200,
            'topic_categories' => [],
            'tags' => ['歌枠', 'karaoke'],
        ]);

        $this->assertSame(ContentKind::Music, $result['kind']);
        $this->assertSame('keyword', $result['signal']);
    }

    #[Test]
    public function latin_keywords_respect_word_boundaries(): void
    {
        // "discover"/"coverage" must not fire the "cover" keyword — the video is
        // long-form, so a false positive here would also be a wrong verdict.
        foreach (['Discovering a new world', 'News coverage of the event'] as $title) {
            $result = $this->classifier->classify([
                'title' => $title,
                'description' => null,
                'category_id' => '24',
                'duration_seconds' => 1800,
                'topic_categories' => [],
                'tags' => [],
            ]);

            $this->assertSame(ContentKind::NonMusic, $result['kind'], $title);
            $this->assertSame('long_form', $result['signal'], $title);
        }
    }

    #[Test]
    public function the_bare_song_character_does_not_trigger_a_keyword_match(): void
    {
        // "歌" alone lives inside 歌枠 / 歌ってみた already; on its own it matches
        // ordinary talk titles, so it must not be a keyword.
        $result = $this->classifier->classify([
            'title' => '歌',
            'description' => null,
            'category_id' => '22',
            'duration_seconds' => 300,
            'topic_categories' => [],
            'tags' => [],
        ]);

        $this->assertNull($result['kind']);
        $this->assertNull($result['signal']);
    }

    #[Test]
    public function a_description_crediting_an_original_with_a_link_is_music(): void
    {
        $result = $this->classifier->classify([
            'title' => '知らない曲',
            'description' => "本家様：https://youtu.be/cm-l2h6GB8Q\nMix: someone",
            'category_id' => '22',
            'duration_seconds' => 200,
            'topic_categories' => [],
            'tags' => [],
        ]);

        $this->assertSame(ContentKind::Music, $result['kind']);
        $this->assertSame('original_credit', $result['signal']);
    }

    #[Test]
    public function a_credit_marker_without_a_video_link_is_not_enough(): void
    {
        // Gaming and promo descriptions say 本家 / original too — without a link
        // to the source video the marker is too weak to flip the layer.
        $result = $this->classifier->classify([
            'title' => '知らない曲',
            'description' => '本家様のアルバムはこちら',
            'category_id' => '22',
            'duration_seconds' => 200,
            'topic_categories' => [],
            'tags' => [],
        ]);

        $this->assertNull($result['kind']);
    }

    #[Test]
    public function long_form_without_signals_is_non_music(): void
    {
        foreach ([
            'The Perfect Start - Minecraft Hardcore Let\'s Play | Ep 1',
            'ASMR Addictive Tapping 1 Hr',
        ] as $title) {
            $result = $this->classifier->classify([
                'title' => $title,
                'description' => 'No music here',
                'category_id' => '20',
                'duration_seconds' => 3600,
                'topic_categories' => [],
                'tags' => [],
            ]);

            $this->assertSame(ContentKind::NonMusic, $result['kind'], $title);
            $this->assertSame('long_form', $result['signal'], $title);
        }
    }

    #[Test]
    public function short_form_without_signals_is_unknown_for_the_ai_audit(): void
    {
        $result = $this->classifier->classify([
            'title' => '星を編む',
            'description' => null,
            'category_id' => '22',
            'duration_seconds' => 210,
            'topic_categories' => [],
            'tags' => [],
        ]);

        $this->assertNull($result['kind']);
        $this->assertNull($result['signal']);
    }

    #[Test]
    public function unknown_duration_without_signals_is_unknown(): void
    {
        $result = $this->classifier->classify([
            'title' => '星を編む',
            'description' => null,
            'category_id' => null,
            'duration_seconds' => null,
            'topic_categories' => [],
            'tags' => [],
        ]);

        $this->assertNull($result['kind']);
    }

    #[Test]
    public function twitch_style_input_without_signals_is_unknown(): void
    {
        $result = $this->classifier->classify([
            'title' => 'Just Chatting',
            'description' => null,
            'category_id' => null,
            'duration_seconds' => null,
            'topic_categories' => [],
            'tags' => [],
        ]);

        $this->assertNull($result['kind']);
    }

    #[Test]
    public function a_twitch_music_category_is_music_without_topics_or_tags(): void
    {
        // Twitch exposes neither topicCategories nor uploader tags, so the
        // stream category is the only music signal a live row carries.
        foreach (['Music', 'Karaoke', 'DJ', 'VTuber Music'] as $category) {
            $result = $this->classifier->classify([
                'title' => 'chill stream',
                'description' => $category,
                'category_id' => null,
                'category_name' => $category,
                'duration_seconds' => null,
                'topic_categories' => [],
                'tags' => [],
            ]);

            $this->assertSame(ContentKind::Music, $result['kind'], $category);
            $this->assertSame('category_name', $result['signal'], $category);
        }
    }

    #[Test]
    public function a_twitch_non_music_category_stays_unknown(): void
    {
        // The category is the only signal here, so a non-music one must not
        // resolve either way — the AI audit owns that row.
        foreach (['Just Chatting', 'Art', 'Valorant', 'Music Production & Beats'] as $category) {
            $result = $this->classifier->classify([
                'title' => 'stream',
                'description' => $category,
                'category_id' => null,
                'category_name' => $category,
                'duration_seconds' => null,
                'topic_categories' => [],
                'tags' => [],
            ]);

            $this->assertNull($result['kind'], $category);
        }
    }

    #[Test]
    public function the_twitch_category_match_is_case_folded_and_exact(): void
    {
        $this->assertSame(ContentKind::Music, $this->classifier->kind([
            'title' => 'stream',
            'category_name' => '  karaoke  ',
        ]));

        // A substring test would swallow this one, and the stream is not music.
        $this->assertNull($this->classifier->kind([
            'title' => 'stream',
            'category_name' => 'Just Chatting & Music Requests',
        ]));
    }

    #[Test]
    public function the_twitch_music_category_list_is_configurable(): void
    {
        config(['vtual.content_classification.twitch_music_categories' => ['Live Performance']]);

        $this->assertSame(ContentKind::Music, $this->classifier->kind([
            'title' => 'stream',
            'category_name' => 'Live Performance',
        ]));

        // The shipped list no longer applies once the config is replaced.
        $this->assertNull($this->classifier->kind([
            'title' => 'stream',
            'category_name' => 'Music',
        ]));
    }

    #[Test]
    public function has_music_category_is_public_for_the_live_mapper(): void
    {
        $this->assertTrue($this->classifier->hasMusicCategory('Music'));
        $this->assertTrue($this->classifier->hasMusicCategory('music'));
        $this->assertFalse($this->classifier->hasMusicCategory('Just Chatting'));
        $this->assertFalse($this->classifier->hasMusicCategory(''));
    }

    #[Test]
    public function the_long_form_threshold_is_configurable(): void
    {
        config(['vtual.content_classification.long_form_min_seconds' => 300]);

        $result = $this->classifier->classify([
            'title' => '星を編む',
            'category_id' => '22',
            'duration_seconds' => 400,
            'topic_categories' => [],
            'tags' => [],
        ]);

        $this->assertSame(ContentKind::NonMusic, $result['kind']);
    }

    #[Test]
    public function kind_convenience_returns_just_the_verdict(): void
    {
        $this->assertSame(ContentKind::Music, $this->classifier->kind([
            'title' => '【MV】オリジナル曲',
            'category_id' => '24',
            'duration_seconds' => 200,
        ]));

        $this->assertNull($this->classifier->kind(['title' => '雑談', 'category_id' => '22', 'duration_seconds' => 200]));
    }
}
