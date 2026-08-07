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
}
