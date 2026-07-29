<?php

namespace App\Domain\Microstock\Exceptions;

use App\Exceptions\BusinessRuleException;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MicrostockException extends BusinessRuleException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        private readonly int $httpStatus = 422,
        private readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponder::error(
            $this->getMessage(),
            $this->statusCode(),
            $this->context !== [] ? $this->context : null,
        );
    }
}
