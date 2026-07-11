<?php

namespace App\Domain\PaymentGateway\Enums;

enum PaymentChannelKind: string
{
    case Qris = 'qris';
    case Va = 'va';
    case Ewallet = 'ewallet';
}
