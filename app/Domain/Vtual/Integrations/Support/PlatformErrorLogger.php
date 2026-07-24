<?php

namespace App\Domain\Vtual\Integrations\Support;

use App\Domain\ErrorLog\Support\ErrorLogRecorder;
use Throwable;

/**
 * Ops-facing platform failure → ErrorLog (recordLog), never throws.
 * PlatformApiException is a BusinessRuleException so recordException would skip it.
 */
final class PlatformErrorLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function log(string $message, array $context = []): void
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            $trimmed = 'Platform integration failure';
        }

        // Never ship long token-like blobs into error_logs.
        if (preg_match('/\b[A-Za-z0-9_\-]{40,}\b/', $trimmed)) {
            $trimmed = preg_replace('/\b[A-Za-z0-9_\-]{40,}\b/', '[redacted]', $trimmed) ?? $trimmed;
        }

        $merged = array_merge([
            'domain' => 'vtual',
            'component' => 'platform_integration',
        ], $context);

        try {
            app(ErrorLogRecorder::class)->recordLog('error', $trimmed, $merged);
        } catch (Throwable) {
            // Never break platform request path.
        }
    }
}
