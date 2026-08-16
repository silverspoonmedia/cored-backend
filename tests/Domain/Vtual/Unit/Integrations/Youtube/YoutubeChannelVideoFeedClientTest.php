<?php

namespace Tests\Domain\Vtual\Unit\Integrations\Youtube;

use App\Domain\Vtual\Integrations\Youtube\YoutubeAtomFeedParser;
use App\Domain\Vtual\Integrations\Youtube\YoutubeChannelVideoFeedClient;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class YoutubeChannelVideoFeedClientTest extends TestCase
{
    #[Test]
    public function topic_url_builds_channel_feed_url(): void
    {
        $client = new YoutubeChannelVideoFeedClient(new YoutubeAtomFeedParser);

        $this->assertSame(
            'https://www.youtube.com/feeds/videos.xml?channel_id=UCPkKpOHxEDcwmUAnRpIu-Ng',
            $client->topicUrl('UCPkKpOHxEDcwmUAnRpIu-Ng'),
        );
    }
}
