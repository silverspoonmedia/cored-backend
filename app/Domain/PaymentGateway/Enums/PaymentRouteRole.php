<?php

namespace App\Domain\PaymentGateway\Enums;

enum PaymentRouteRole: string
{
    case Primary = 'primary';
    case Fallback = 'fallback';
    case Only = 'only';
}
