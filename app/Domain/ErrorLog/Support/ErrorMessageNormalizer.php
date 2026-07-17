<?php

namespace App\Domain\ErrorLog\Support;

/**
 * Stabilize free-text error messages so fingerprints group related events.
 */
class ErrorMessageNormalizer
{
    public function normalize(?string $message): string
    {
        $message = trim((string) $message);

        if ($message === '') {
            return '';
        }

        // UUIDs (any version)
        $message = (string) preg_replace(
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
            '{uuid}',
            $message,
        );

        // Emails
        $message = (string) preg_replace(
            '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i',
            '{email}',
            $message,
        );

        // Hex hashes / tokens
        $message = (string) preg_replace('/\b[0-9a-f]{32,}\b/i', '{hash}', $message);

        // Standalone numbers (IDs, amounts, line numbers in messages)
        $message = (string) preg_replace('/\b\d+\b/', '{n}', $message);

        // Collapse whitespace
        $message = (string) preg_replace('/\s+/', ' ', $message);

        return trim($message);
    }
}
