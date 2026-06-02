<?php

namespace App\Exceptions\Auth;

use App\Exceptions\BusinessRuleException;

class InvalidPasswordResetTokenException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct('This password reset token is invalid or has expired.');
    }

    public function statusCode(): int
    {
        return 422;
    }
}
