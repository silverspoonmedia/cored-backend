<?php

namespace App\Domain\Vtual\Enums;

enum TwitchEventSubSkipReason: string
{
    case UnknownSubscription = 'unknown_subscription';
    case BadSignature = 'bad_signature';
    case EmptyBody = 'empty_body';
    case ParseFailed = 'parse_failed';
    case OversizedBody = 'oversized_body';
    case DuplicateMessage = 'duplicate_message';
}
