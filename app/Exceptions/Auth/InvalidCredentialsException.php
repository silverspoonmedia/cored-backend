<?php

namespace App\Exceptions\Auth;

use App\Exceptions\BusinessRuleException;

class InvalidCredentialsException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct('Invalid email or password.');
    }

    public function statusCode(): int
    {
        return 401;
    }
}
