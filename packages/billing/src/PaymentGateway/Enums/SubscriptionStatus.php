<?php

namespace App\Domain\PaymentGateway\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Suspended = 'suspended';
}
