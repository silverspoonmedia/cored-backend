<?php

namespace App\Domain\FileHosting\Exceptions;

use App\Exceptions\BusinessRuleException;

class FileHostingException extends BusinessRuleException
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
