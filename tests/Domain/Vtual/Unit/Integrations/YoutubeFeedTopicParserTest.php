<?php

namespace Tests\Domain\Vtual\Unit\Integrations;

use App\Domain\Vtual\Integrations\Youtube\YoutubeFeedTopicParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class YoutubeFeedTopicParserTest extends TestCase
{
    #[Test]
    public function parse_channel_external_id_from_topic_url(): void
    {
        $parser = new YoutubeFeedTopicParser;

        $id = $parser->parseChannelExternalId(
            'https://www.youtube.com/feeds/videos.xml?channel_id=UCPkKpOHxEDcwmUAnRpIu-Ng'
        );

        $this->assertSame('UCPkKpOHxEDcwmUAnRpIu-Ng', $id);
    }

    #[Test]
    public function parse_channel_external_id_returns_null_for_invalid_topic(): void
    {
        $parser = new YoutubeFeedTopicParser;

        $this->assertNull($parser->parseChannelExternalId('https://example.test/feed'));
    }
}
