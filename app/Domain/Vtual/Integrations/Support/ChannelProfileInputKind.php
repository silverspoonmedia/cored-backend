<?php

namespace App\Domain\Vtual\Integrations\Support;

enum ChannelProfileInputKind: string
{
    case ChannelId = 'channel_id';
    case Handle = 'handle';
}
