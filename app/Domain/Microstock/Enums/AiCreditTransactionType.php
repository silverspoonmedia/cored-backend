<?php

namespace App\Domain\Microstock\Enums;

enum AiCreditTransactionType: string
{
    case Purchase = 'purchase';
    case AnalysisDebit = 'analysis_debit';
    case Adjustment = 'adjustment';
}
