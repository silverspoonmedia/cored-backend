<?php

namespace App\Domain\Ai\Support;

use App\Domain\Ai\Models\AiConnection;

/**
 * Outcome of a manual connection Test (never includes the key).
 */
final readonly class AiConnectionTestResult
{
    public function __construct(
        public bool $ok,
        public string $message,
        public ?int $latencyMs = null,
        public ?AiConnection $connection = null,
    ) {}

    /**
     * @return array{ok: bool, message: string, latency_ms: int|null}
     */
    public function toPublicArray(): array
    {
        return [
            'ok' => $this->ok,
            'message' => $this->message,
            'latency_ms' => $this->latencyMs,
        ];
    }
}
