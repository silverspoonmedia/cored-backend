<?php

namespace App\Domain\Vtual\Enums;

enum WebsubSubscriptionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Failed = 'failed';
    case Unsubscribed = 'unsubscribed';
}
