<?php

namespace App\Domain\Vtual\Integrations\Enums;

enum PlatformApiOperation: string
{
    case FetchVideos = 'fetch_videos';
    case FetchChannels = 'fetch_channels';
    case ResolveChannel = 'resolve_channel';
    case FetchStreams = 'fetch_streams';
    case ListChannelVideos = 'list_channel_videos';
}
