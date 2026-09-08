<?php

namespace App\Domain\Archivd\Enums;

enum FileVariantStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
}
