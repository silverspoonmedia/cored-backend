<?php

namespace App\Domain\Vtual\Services;

enum CreatorClaimProcessResult: string
{
    case Verified = 'verified';
    case TokenNotFound = 'token_not_found';
}
