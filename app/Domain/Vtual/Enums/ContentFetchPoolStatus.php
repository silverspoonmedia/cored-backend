<?php

namespace App\Domain\Vtual\Enums;

enum ContentFetchPoolStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';
}
