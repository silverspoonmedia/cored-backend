<?php

namespace App\Domain\Service\Exceptions;

use App\Exceptions\BusinessRuleException;

class ServiceException extends BusinessRuleException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 422,
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->httpStatus;
    }
}
