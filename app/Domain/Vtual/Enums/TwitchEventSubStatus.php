<?php

namespace App\Domain\Vtual\Enums;

enum TwitchEventSubStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Failed = 'failed';
    case Unsubscribed = 'unsubscribed';
}
