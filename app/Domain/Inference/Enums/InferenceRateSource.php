<?php

namespace App\Domain\Inference\Enums;

enum InferenceRateSource: string
{
    case Known = 'known';
    case UnknownProvisional = 'unknown_provisional';
    case ConfigDefault = 'config_default';
}
