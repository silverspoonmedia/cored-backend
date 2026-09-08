<?php

namespace Tests\Domain\Vtual\Unit\Integrations;

use App\Domain\Vtual\Integrations\Youtube\YoutubeAtomFeedParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class YoutubeAtomFeedParserTest extends TestCase
{
    private YoutubeAtomFeedParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new YoutubeAtomFeedParser;
    }

    #[Test]
    public function parse_entries_reads_fixture_feed(): void
    {
        $body = file_get_contents(base_path('tests/fixtures/vtual/youtube-channel-feed.xml'));
        $this->assertIsString($body);

        $entries = $this->parser->parseEntries($body);

        $this->assertCount(2, $entries);
        $this->assertSame('VgdAA0Gdzn0', $entries->first()?->videoId);
    }

    #[Test]
    public function extract_video_ids_returns_unique_ids(): void
    {
        $body = file_get_contents(base_path('tests/fixtures/vtual/youtube-channel-feed.xml'));
        $this->assertIsString($body);

        $ids = $this->parser->extractVideoIds($body);

        $this->assertSame(['VgdAA0Gdzn0', 'olderVideoId12'], $ids->all());
    }

    #[Test]
    public function parse_entries_returns_empty_for_malformed_xml(): void
    {
        $entries = $this->parser->parseEntries('<not-xml');

        $this->assertTrue($entries->isEmpty());
    }

    #[Test]
    public function parse_entries_throws_when_invalid_and_strict(): void
    {
        $this->expectException(\App\Domain\Vtual\Exceptions\VtualException::class);
        $this->parser->parseEntries('<not-xml', throwOnInvalid: true);
    }

    #[Test]
    public function extract_deleted_video_ids_reads_tombstones(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<feed xmlns:at="http://www.youtube.com/xml/schemas/2015" xmlns="http://www.w3.org/2005/Atom">'
            .'<at:deleted-entry ref="yt:video:goneVideoId1" when_deleted="2026-09-01T00:00:00+00:00"/>'
            .'<at:deleted-entry ref="yt:video:goneVideoId2"/>'
            .'<at:deleted-entry ref="yt:other:ignored"/>'
            .'</feed>';

        $ids = $this->parser->extractDeletedVideoIds($xml);

        $this->assertSame(['goneVideoId1', 'goneVideoId2'], $ids->all());
    }

    #[Test]
    public function extract_deleted_video_ids_empty_for_normal_feed(): void
    {
        $body = file_get_contents(base_path('tests/fixtures/vtual/youtube-channel-feed.xml'));
        $this->assertIsString($body);

        $this->assertTrue($this->parser->extractDeletedVideoIds($body)->isEmpty());
    }
}
