<?php

namespace Tests\Domain\Vtual\Unit\Integrations\Youtube;

use App\Domain\Vtual\Integrations\Youtube\YoutubeChannelVideoFeedClient;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class YoutubeChannelVideoFeedClientTest extends TestCase
{
    #[Test]
    public function parse_feed_fixture_extracts_video_ids_and_timestamps(): void
    {
        $xml = file_get_contents(base_path('tests/fixtures/vtual/youtube-channel-feed.xml'));
        $this->assertIsString($xml);

        $client = new class extends YoutubeChannelVideoFeedClient
        {
            public function parseForTest(string $xml)
            {
                $ref = new \ReflectionClass(YoutubeChannelVideoFeedClient::class);
                $method = $ref->getMethod('parseFeed');
                $method->setAccessible(true);

                return $method->invoke($this, $xml);
            }
        };

        $entries = $client->parseForTest($xml);

        $this->assertCount(2, $entries);
        $this->assertSame('VgdAA0Gdzn0', $entries->first()?->videoId);
        $this->assertSame('2026-08-06T08:30:00+00:00', $entries->first()?->updatedAt?->toIso8601String());
    }

    #[Test]
    public function topic_url_builds_channel_feed_url(): void
    {
        $client = new YoutubeChannelVideoFeedClient;

        $this->assertSame(
            'https://www.youtube.com/feeds/videos.xml?channel_id=UCPkKpOHxEDcwmUAnRpIu-Ng',
            $client->topicUrl('UCPkKpOHxEDcwmUAnRpIu-Ng'),
        );
    }
}
