<?php

namespace App\Domain\Vtual\Enums;

enum YoutubeWebSubSkipReason: string
{
    case UnknownTopic = 'unknown_topic';
    case BadSignature = 'bad_signature';
    case EmptyBody = 'empty_body';
    case ParseFailed = 'parse_failed';
    case OversizedBody = 'oversized_body';
}
