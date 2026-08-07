<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

class IdentityOAuthException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 422,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
