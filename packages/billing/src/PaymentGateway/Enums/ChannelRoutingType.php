<?php

namespace App\Domain\PaymentGateway\Enums;

enum ChannelRoutingType: string
{
    case Single = 'single';
    case Dual = 'dual';
    case QrisProxy = 'qris_proxy';
}
