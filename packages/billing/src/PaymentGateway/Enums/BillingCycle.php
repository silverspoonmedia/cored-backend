<?php

namespace App\Domain\PaymentGateway\Enums;

enum BillingCycle: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';
}
