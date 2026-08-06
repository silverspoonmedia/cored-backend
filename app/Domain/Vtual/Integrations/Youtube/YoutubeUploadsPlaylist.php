<?php

namespace App\Domain\Vtual\Integrations\Youtube;

use App\Domain\Vtual\Exceptions\VtualException;

/**
 * Derive the YouTube uploads playlist id from a channel id (UC… → UU…).
 */
final class YoutubeUploadsPlaylist
{
    public static function fromChannelId(string $channelExternalId): string
    {
        $trimmed = trim($channelExternalId);
        if ($trimmed === '' || ! str_starts_with($trimmed, 'UC')) {
            throw new VtualException(__('api/vtual.integrations.youtube_channel_id_required'), 422);
        }

        return 'UU'.substr($trimmed, 2);
    }
}
