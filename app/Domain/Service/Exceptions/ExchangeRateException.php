<?php

namespace App\Domain\Service\Exceptions;

use App\Exceptions\BusinessRuleException;

class ExchangeRateException extends BusinessRuleException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 502,
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->httpStatus;
    }
}
