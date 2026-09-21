<?php

namespace App\Domain\Ai\Exceptions;

use App\Exceptions\BusinessRuleException;

class AiException extends BusinessRuleException
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
