<?php

namespace App\Exceptions\Auth;

use App\Exceptions\BusinessRuleException;

class EmailAlreadyVerifiedException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct('Email address is already verified.');
    }

    public function statusCode(): int
    {
        return 422;
    }
}
