<?php

namespace App\Domain\Microstock\Enums;

enum MetadataStatus: string
{
    case Pending = 'pending';
    case Generated = 'generated';
    case Finalized = 'finalized';
    case Failed = 'failed';
}
