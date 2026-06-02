<?php

namespace App\Exceptions\Playground;

use App\Exceptions\BusinessRuleException;

class DisposableEmailNotAllowedException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct('Disposable email domains are not allowed.');
    }

    public function statusCode(): int
    {
        return 422;
    }
}
