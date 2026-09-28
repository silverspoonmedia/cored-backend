<?php

namespace App\Domain\Vtual\Integrations\Support;

use App\Domain\Vtual\Enums\IncidentKind;
use App\Domain\Vtual\Enums\IncidentSeverity;
use App\Domain\Vtual\Exceptions\PlatformApiException;
use App\Domain\Vtual\Support\VtualIncidentRecorder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Shared HTTP helpers for platform clients. Never put secrets in exception messages.
 * Failed responses are recorded to ErrorLog for ops (credential / quota decisions).
 *
 * Terminal failures (a call that spent its retry budget, or a connection error)
 * also raise a `platform_http` incident. The subject here is the **platform
 * key**, not a channel or credential: this layer has no row context, and the
 * callers that do (WebSub/EventSub/credential paths) raise their own, more
 * specific incident alongside it.
 */
final class PlatformHttp
{
    public static function client(?int $timeout = null): PendingRequest
    {
        $seconds = $timeout ?? (int) setting_config('vtual.http.timeout', 20);

        return Http::timeout(max(1, $seconds))->acceptJson();
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $logContext  Ops context only (credential_id, operation). Never secrets.
     */
    public static function get(
        string $url,
        array $query,
        ?PendingRequest $request = null,
        ?string $platformKey = null,
        array $logContext = [],
    ): Response {
        $pending = $request ?? self::client();
        $maxAttempts = self::maxAttempts();
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                // Empty query array must not be passed — Guzzle replaces URL query with [].
                $response = $query === []
                    ? $pending->get($url)
                    : $pending->get($url, $query);
            } catch (ConnectionException $e) {
                $message = __('api/vtual.integrations.upstream_connection_error', [
                    'platform' => $platformKey ?? 'platform',
                ]);
                self::logFailure($message, $platformKey, null, $url, $logContext, [
                    'event' => 'platform_http_connection_failed',
                    'attempt' => $attempt,
                    'exception_class' => $e::class,
                ]);

                throw new PlatformApiException(
                    message: $message,
                    httpStatus: 502,
                    upstreamStatus: null,
                    platformKey: $platformKey,
                );
            }

            if ($response->successful()) {
                return $response;
            }

            $status = $response->status();

            if (self::shouldRetry($status) && $attempt < $maxAttempts) {
                $delayMs = self::retryDelayMs($response, $attempt);

                self::logFailure(
                    __('api/vtual.integrations.upstream_retry_scheduled', [
                        'platform' => $platformKey ?? 'platform',
                        'status' => $status,
                        'attempt' => $attempt,
                        'delay' => $delayMs,
                    ]),
                    $platformKey,
                    $status,
                    $url,
                    $logContext,
                    [
                        'event' => 'platform_http_retry_scheduled',
                        'attempt' => $attempt,
                        'max_attempts' => $maxAttempts,
                        'delay_ms' => $delayMs,
                    ],
                );

                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }

                continue;
            }

            // 429 that survived the retry budget is quota exhaustion, not a hard
            // failure: distinct message so ops can tell the two apart.
            $message = $status === 429
                ? __('api/vtual.integrations.upstream_quota_exhausted', [
                    'platform' => $platformKey ?? 'platform',
                ])
                : self::safeErrorMessage($response, $platformKey, $status);

            self::logFailure($message, $platformKey, $status, $url, $logContext, [
                'event' => $status === 429 ? 'platform_http_quota_exhausted' : 'platform_http_failed',
                'attempt' => $attempt,
                'max_attempts' => $maxAttempts,
            ]);

            throw new PlatformApiException(
                message: $message,
                httpStatus: $status === 401 || $status === 403 ? 422 : 502,
                upstreamStatus: $status,
                platformKey: $platformKey,
            );
        }
    }

    /**
     * 429 and 5xx are transient upstream conditions worth a retry. 401/403 are
     * owned by the credential failover path; every other 4xx is a hard client
     * error that a retry cannot fix.
     */
    private static function shouldRetry(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }

    private static function maxAttempts(): int
    {
        return max(1, (int) setting_config('vtual.http.retry.times', 3));
    }

    /**
     * Exponential backoff from `base_ms`, capped at `max_ms`, honouring a sane
     * `Retry-After` header. Full jitter spreads a fleet-wide quota hit so the
     * retries do not resynchronise into a second burst.
     */
    private static function retryDelayMs(Response $response, int $attempt): int
    {
        $base = max(1, (int) setting_config('vtual.http.retry.base_ms', 250));
        $cap = max($base, (int) setting_config('vtual.http.retry.max_ms', 8000));
        $jitter = (bool) setting_config('vtual.http.retry.jitter', true);

        $backoff = min($cap, $base * (2 ** max(0, $attempt - 1)));

        $retryAfter = self::retryAfterMs($response);
        if ($retryAfter !== null) {
            return min($cap, $retryAfter);
        }

        return $jitter ? random_int(0, $backoff) : $backoff;
    }

    private static function retryAfterMs(Response $response): ?int
    {
        $header = trim((string) $response->header('Retry-After'));

        if ($header === '') {
            return null;
        }

        if (ctype_digit($header)) {
            $seconds = (int) $header;

            // Absurd values mean the upstream is confused, not that we should
            // sleep for an hour inside a request.
            return $seconds >= 1 && $seconds <= 60 ? $seconds * 1000 : null;
        }

        $timestamp = strtotime($header);
        if ($timestamp === false) {
            return null;
        }

        $seconds = $timestamp - time();

        return $seconds >= 1 && $seconds <= 60 ? $seconds * 1000 : null;
    }

    /**
     * @param  array<string, mixed>  $logContext
     * @param  array<string, mixed>  $extra
     */
    private static function logFailure(
        string $message,
        ?string $platformKey,
        ?int $upstreamStatus,
        string $url,
        array $logContext,
        array $extra = [],
    ): void {
        $parts = parse_url($url);

        PlatformErrorLogger::log($message, array_merge([
            'component' => 'platform_http',
            'platform' => $platformKey,
            'upstream_status' => $upstreamStatus,
            'url_host' => is_array($parts) && isset($parts['host']) ? (string) $parts['host'] : null,
            'url_path' => is_array($parts) && isset($parts['path']) ? (string) $parts['path'] : null,
        ], $extra, $logContext));

        // A scheduled retry is a transient note, not an incident — only the
        // terminal failures reach the triage queue.
        if (($extra['event'] ?? null) === 'platform_http_retry_scheduled') {
            return;
        }

        app(VtualIncidentRecorder::class)->record(
            kind: IncidentKind::PlatformHttp,
            severity: IncidentSeverity::Warning,
            title: 'Platform API call failed ('.($platformKey ?? 'unknown').')',
            detail: $message,
            context: array_merge([
                'platform' => $platformKey,
                'upstream_status' => $upstreamStatus,
                'url_host' => is_array($parts) && isset($parts['host']) ? (string) $parts['host'] : null,
                'url_path' => is_array($parts) && isset($parts['path']) ? (string) $parts['path'] : null,
            ], $extra, $logContext),
        );
    }

    private static function safeErrorMessage(Response $response, ?string $platformKey, int $status): string
    {
        $body = $response->json();
        $hint = null;

        if (is_array($body)) {
            // YouTube error shape
            if (is_array($body['error'] ?? null)) {
                $hint = $body['error']['message'] ?? $body['error']['errors'][0]['reason'] ?? null;
            }
            // Twitch error shape
            if ($hint === null) {
                $hint = $body['message'] ?? $body['error'] ?? null;
            }
        }

        $hintStr = is_string($hint) ? trim($hint) : '';
        // Strip anything that looks like a long token from error text.
        if ($hintStr !== '' && preg_match('/\b[A-Za-z0-9_\-]{40,}\b/', $hintStr)) {
            $hintStr = '';
        }

        $platform = $platformKey ?? 'platform';
        if ($hintStr !== '') {
            return __('api/vtual.integrations.upstream_error_detail', [
                'platform' => $platform,
                'status' => $status,
                'detail' => mb_substr($hintStr, 0, 200),
            ]);
        }

        return __('api/vtual.integrations.upstream_error', [
            'platform' => $platform,
            'status' => $status,
        ]);
    }
}
