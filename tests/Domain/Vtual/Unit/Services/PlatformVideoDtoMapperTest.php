<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Integrations\Dto\PlatformStreamDto;
use App\Domain\Vtual\Integrations\Dto\PlatformVideoDto;
use App\Domain\Vtual\Services\PlatformVideoDtoMapper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlatformVideoDtoMapperTest extends TestCase
{
    private PlatformVideoDtoMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = app(PlatformVideoDtoMapper::class);
    }

    #[Test]
    public function maps_uploaded_vod(): void
    {
        $payload = $this->mapper->toUpsertPayload($this->dto('uploaded'));

        $this->assertSame('vid1', $payload['external_id']);
        $this->assertSame(ContentType::Vod->value, $payload['type']);
        $this->assertSame(ContentStatus::Uploaded->value, $payload['status']);
        $this->assertSame('Title', $payload['title']);
    }

    #[Test]
    public function maps_live_and_upcoming_and_ended(): void
    {
        $live = $this->mapper->toUpsertPayload($this->dto('live'));
        $this->assertSame(ContentType::Live->value, $live['type']);
        $this->assertSame(ContentStatus::Live->value, $live['status']);

        $upcoming = $this->mapper->toUpsertPayload($this->dto('upcoming'));
        $this->assertSame(ContentType::Live->value, $upcoming['type']);
        $this->assertSame(ContentStatus::Scheduled->value, $upcoming['status']);
        $this->assertNotNull($upcoming['scheduled_at']);

        $ended = $this->mapper->toUpsertPayload($this->dto('ended'));
        $this->assertSame(ContentType::Archive->value, $ended['type']);
        $this->assertSame(ContentStatus::Ended->value, $ended['status']);
    }

    #[Test]
    public function maps_unknown_status_to_vod_unknown(): void
    {
        $payload = $this->mapper->toUpsertPayload($this->dto('weird'));

        $this->assertSame(ContentType::Vod->value, $payload['type']);
        $this->assertSame(ContentStatus::Unknown->value, $payload['status']);
    }

    #[Test]
    public function resolves_music_from_the_music_topic_before_category_id(): void
    {
        // The real-world false negative: a vTuber cover filed under 22
        // (People & Blogs) that still carries the YouTube Music topic.
        $cover = $this->mapper->toUpsertPayload($this->dto(
            'uploaded',
            categoryId: '22',
            topicCategories: ['https://en.wikipedia.org/wiki/Music'],
        ));
        $this->assertSame('music', $cover['content_type']);

        $gaming = $this->mapper->toUpsertPayload($this->dto(
            'uploaded',
            categoryId: '20',
            topicCategories: ['https://en.wikipedia.org/wiki/Music'],
        ));
        $this->assertSame('music', $gaming['content_type']);
    }

    #[Test]
    public function resolves_music_from_the_category_id_when_no_topic_is_present(): void
    {
        $music = $this->mapper->toUpsertPayload($this->dto('uploaded', categoryId: '10'));
        $this->assertSame('music', $music['content_type']);
    }

    #[Test]
    public function resolves_music_from_a_title_or_tag_keyword(): void
    {
        $tagged = $this->mapper->toUpsertPayload($this->dto(
            'uploaded',
            categoryId: '24',
            tags: ['歌枠', 'karaoke'],
        ));
        $this->assertSame('music', $tagged['content_type']);
    }

    #[Test]
    public function resolves_long_form_without_signals_to_non_music(): void
    {
        $nonMusic = $this->mapper->toUpsertPayload($this->dto('uploaded', categoryId: '24', durationSeconds: 3600));
        $this->assertSame('non_music', $nonMusic['content_type']);
    }

    #[Test]
    public function leaves_short_form_without_signals_unknown_for_the_ai_audit(): void
    {
        // Short + no signal is exactly the bucket the async AI pass exists for;
        // guessing non_music here is the false negative this layer removes.
        $unknown = $this->mapper->toUpsertPayload($this->dto('uploaded', categoryId: null));
        $this->assertNull($unknown['content_type']);

        $short = $this->mapper->toUpsertPayload($this->dto('uploaded', categoryId: '22', durationSeconds: 180));
        $this->assertNull($short['content_type']);
    }

    #[Test]
    public function carries_the_signals_the_content_service_fallback_needs(): void
    {
        $payload = $this->mapper->toUpsertPayload($this->dto(
            'uploaded',
            categoryId: '22',
            durationSeconds: 180,
            topicCategories: ['https://en.wikipedia.org/wiki/Music'],
            tags: ['cover'],
        ));

        $this->assertSame('22', $payload['category_id']);
        $this->assertSame(180, $payload['duration_seconds']);
        $this->assertSame(['https://en.wikipedia.org/wiki/Music'], $payload['topic_categories']);
        $this->assertSame(['cover'], $payload['tags']);
    }

    #[Test]
    public function classifies_a_talk_video_without_signals_as_unknown(): void
    {
        Http::fake();

        $payload = $this->mapper->toUpsertPayload($this->dto('uploaded', categoryId: '24', durationSeconds: 3600));

        $this->assertNull($payload['content_category']);
    }

    #[Test]
    public function resolves_cover_from_uploader_tags(): void
    {
        Http::fake();

        $payload = $this->mapper->toUpsertPayload($this->dto(
            'uploaded',
            categoryId: '24',
            tags: ['Vtuber', '歌ってみた'],
            title: '君の知らない物語',
        ));

        $this->assertSame('cover', $payload['content_category']);
    }

    #[Test]
    public function resolves_original_when_the_catalog_artist_is_the_uploader(): void
    {
        // Keyword-free original: no title/tag signal at all, so the catalog
        // artist check is the only thing that can resolve it without the AI audit.
        Http::fake([
            'itunes.apple.com/search*' => Http::response([
                'results' => [
                    ['trackName' => 'KING', 'artistName' => 'Kanaria'],
                ],
            ], 200),
        ]);

        $payload = $this->mapper->toUpsertPayload($this->dto(
            'uploaded',
            categoryId: '10',
            title: '【GUMI】KING【Kanaria】',
            channelTitle: 'Kanaria',
        ));

        $this->assertSame('original', $payload['content_category']);
    }

    #[Test]
    public function resolves_cover_when_the_catalog_artist_differs_from_the_uploader(): void
    {
        Http::fake([
            'itunes.apple.com/search*' => Http::response([
                'results' => [
                    ['trackName' => 'KING', 'artistName' => 'Kanaria'],
                ],
            ], 200),
        ]);

        $payload = $this->mapper->toUpsertPayload($this->dto(
            'uploaded',
            categoryId: '10',
            title: '【GUMI】KING【Kanaria】',
            channelTitle: '橘ひなの',
        ));

        $this->assertSame('cover', $payload['content_category']);
    }

    #[Test]
    public function a_feat_credit_alone_does_not_make_an_original_a_cover(): void
    {
        Http::fake();

        $payload = $this->mapper->toUpsertPayload($this->dto(
            'uploaded',
            categoryId: '10',
            durationSeconds: 238,
            title: 'ジャバウォック / wotaku feat. 初音ミク',
            description: 'この歌は終わらないから 森の動物と歌おう',
        ));

        $this->assertSame('music', $payload['content_type']);
        $this->assertNull($payload['content_category']);
    }

    #[Test]
    public function maps_stream_to_live_payload(): void
    {
        $stream = new PlatformStreamDto(
            platform: PlatformKey::Twitch,
            externalId: 'stream-123',
            userExternalId: '40934651',
            userLogin: 'ludwig',
            userName: 'Ludwig',
            title: 'Twitch live title',
            gameName: 'Just Chatting',
            thumbnailUrl: 'https://static-cdn.jtvnw.net/thumb.jpg',
            url: 'https://www.twitch.tv/ludwig',
            concurrentViewers: 1234,
            startedAt: Carbon::parse('2026-01-01T12:00:00Z'),
            language: 'en',
        );

        $payload = $this->mapper->toUpsertPayloadFromStream($stream);

        $this->assertSame('stream-123', $payload['external_id']);
        $this->assertSame(ContentType::Live->value, $payload['type']);
        $this->assertSame(ContentStatus::Live->value, $payload['status']);
        $this->assertSame('Twitch live title', $payload['title']);
        $this->assertSame('Just Chatting', $payload['description']);
        $this->assertSame('https://www.twitch.tv/ludwig', $payload['url']);
        $this->assertSame(1234, $payload['concurrent_viewers']);
        $this->assertSame($stream->startedAt, $payload['started_at']);
        $this->assertSame($stream->startedAt, $payload['published_at']);
        $this->assertArrayNotHasKey('ended_at', $payload);
        $this->assertArrayNotHasKey('scheduled_at', $payload);
    }

    /**
     * @param  list<string>  $topicCategories
     * @param  list<string>  $tags
     */
    private function dto(
        string $status,
        ?string $categoryId = '10',
        int $durationSeconds = 120,
        array $topicCategories = [],
        array $tags = [],
        string $title = 'Title',
        string $description = 'Desc',
        ?string $channelTitle = null,
    ): PlatformVideoDto {
        return new PlatformVideoDto(
            platform: PlatformKey::Youtube,
            externalId: 'vid1',
            channelExternalId: 'UCabc',
            title: $title,
            description: $description,
            thumbnailUrl: 'https://img.example/thumb.jpg',
            url: 'https://www.youtube.com/watch?v=vid1',
            status: $status,
            publishedAt: Carbon::parse('2026-01-01T00:00:00Z'),
            startedAt: Carbon::parse('2026-01-01T12:00:00Z'),
            endedAt: null,
            durationSeconds: $durationSeconds,
            viewCount: 10,
            concurrentViewers: null,
            categoryId: $categoryId,
            topicCategories: $topicCategories,
            tags: $tags,
            channelTitle: $channelTitle,
        );
    }
}
