<?php

namespace App\Exceptions\Playground;

use App\Exceptions\BusinessRuleException;

class CannotDeleteLastRemainingUserException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct(__('api/playground.cannot_delete_last_user'));
    }

    public function statusCode(): int
    {
        return 409;
    }
}
