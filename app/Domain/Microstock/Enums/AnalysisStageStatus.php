<?php

namespace App\Domain\Microstock\Enums;

enum AnalysisStageStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Ok = 'ok';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
