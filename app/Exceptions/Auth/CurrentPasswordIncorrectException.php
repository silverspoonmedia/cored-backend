<?php

namespace App\Exceptions\Auth;

use App\Exceptions\BusinessRuleException;

class CurrentPasswordIncorrectException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct('The current password is incorrect.');
    }

    public function statusCode(): int
    {
        return 422;
    }
}
