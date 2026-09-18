<?php

namespace App\Domain\Ai\Support;

use App\Domain\Ai\Enums\AiConnectionDriver;

/**
 * Resolved, ready-to-call AI connection. Plain values only — no Eloquent model
 * leaks into consumer code, and the decrypted key never lands in a log payload.
 */
final readonly class AiConnectionData
{
    /**
     * @param  array<string, string>  $headers  Extra headers beyond the bearer token
     */
    public function __construct(
        public string $id,
        public string $slug,
        public AiConnectionDriver $driver,
        public string $endpoint,
        public string $apiKey,
        public ?int $timeoutSeconds = null,
        public ?int $connectTimeoutSeconds = null,
        public ?int $maxRetries = null,
        public array $headers = [],
    ) {}
}
