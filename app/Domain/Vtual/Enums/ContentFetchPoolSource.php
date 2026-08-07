<?php

namespace App\Domain\Vtual\Enums;

enum ContentFetchPoolSource: string
{
    case FeedPoll = 'feed_poll';
    case Websub = 'websub';
    case Manual = 'manual';
    case Reconcile = 'reconcile';
}
