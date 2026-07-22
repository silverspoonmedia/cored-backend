<?php

namespace App\Domain\Vtual\Exceptions;

/**
 * Upstream platform HTTP / client errors. Message must never include secrets/tokens.
 */
class PlatformApiException extends VtualException
{
    public function __construct(
        string $message,
        int $httpStatus = 502,
        public readonly ?int $upstreamStatus = null,
        public readonly ?string $platformKey = null,
    ) {
        parent::__construct($message, $httpStatus);
    }
}
