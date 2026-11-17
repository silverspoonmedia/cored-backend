<?php

namespace App\Domain\Service\Exceptions;

use App\Exceptions\BusinessRuleException;

class DuplicateServiceCodeException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct(__('api/services.duplicate_code'));
    }

    public function statusCode(): int
    {
        return 422;
    }
}
