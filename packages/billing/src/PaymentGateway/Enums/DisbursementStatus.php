<?php

namespace App\Domain\PaymentGateway\Enums;

enum DisbursementStatus: string
{
    case None = 'none';
    case Eligible = 'eligible';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
