<?php

namespace App\Domain\Vtual\Exceptions;

use App\Exceptions\BusinessRuleException;

class VtualException extends BusinessRuleException
{
    public function __construct(string $message, private readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->httpStatus;
    }
}
