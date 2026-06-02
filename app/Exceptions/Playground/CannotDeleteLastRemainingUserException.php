<?php

namespace App\Exceptions\Playground;

use App\Exceptions\BusinessRuleException;

class CannotDeleteLastRemainingUserException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct('Cannot delete the last remaining user.');
    }

    public function statusCode(): int
    {
        return 409;
    }
}
