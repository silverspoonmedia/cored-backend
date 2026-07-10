<?php

namespace App\Domain\Playground\Exceptions;

use App\Exceptions\BusinessRuleException;

class DisposableEmailNotAllowedException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct(__('api/playground.disposable_email'));
    }

    public function statusCode(): int
    {
        return 422;
    }
}
