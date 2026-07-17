<?php

namespace App\Domain\ErrorLog\Support;

/**
 * Builds a stable fingerprint for grouping error occurrences.
 */
class ErrorFingerprint
{
    public function __construct(
        private readonly ErrorMessageNormalizer $normalizer,
    ) {}

    public function make(
        string $type,
        string $level,
        ?string $exceptionClass,
        string $message,
        ?string $file,
        ?int $line,
    ): string {
        $parts = [
            $type,
            strtolower($level),
            $exceptionClass ?? '',
            $this->normalizer->normalize($message),
            $file ?? '',
            (string) ($line ?? ''),
        ];

        return hash('sha1', implode('|', $parts));
    }
}
