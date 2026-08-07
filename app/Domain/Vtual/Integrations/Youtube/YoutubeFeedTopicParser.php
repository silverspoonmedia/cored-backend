<?php

namespace App\Domain\Vtual\Integrations\Youtube;

/**
 * Parse YouTube WebSub topic URLs (feeds/videos.xml?channel_id=UC...).
 */
class YoutubeFeedTopicParser
{
    public function parseChannelExternalId(string $topic): ?string
    {
        $topic = trim($topic);
        if ($topic === '') {
            return null;
        }

        $query = parse_url($topic, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);
        $channelId = trim((string) ($params['channel_id'] ?? ''));

        if ($channelId === '' || ! str_starts_with($channelId, 'UC')) {
            return null;
        }

        return $channelId;
    }
}
