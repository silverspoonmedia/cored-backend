<?php

namespace App\Domain\FileHosting\Enums;

enum FileVariantStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
}
