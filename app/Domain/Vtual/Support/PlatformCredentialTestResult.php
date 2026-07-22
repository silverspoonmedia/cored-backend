<?php

namespace App\Domain\Vtual\Support;

use App\Domain\Vtual\Models\PlatformCredential;

/**
 * Outcome of a manual credential Test (never includes secrets/tokens).
 */
final class PlatformCredentialTestResult
{
    /**
     * @param  array<string, mixed>|null  $runtime  System runtime payload to persist (Twitch token etc.)
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $message,
        public readonly string $platformKey,
        public readonly ?array $runtime = null,
        public readonly ?PlatformCredential $credential = null,
    ) {}

    /**
     * @return array{ok: bool, message: string, platform: string}
     */
    public function toPublicArray(): array
    {
        return [
            'ok' => $this->ok,
            'message' => $this->message,
            'platform' => $this->platformKey,
        ];
    }
}
