<?php

namespace App\Exceptions\Navigation;

use App\Exceptions\BusinessRuleException;

class InvalidMenuParentException extends BusinessRuleException
{
    public function statusCode(): int
    {
        return 422;
    }
}
