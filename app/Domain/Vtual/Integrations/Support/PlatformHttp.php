<?php

namespace App\Domain\Vtual\Integrations\Support;

use App\Domain\Vtual\Exceptions\PlatformApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Shared HTTP helpers for platform clients. Never put secrets in exception messages.
 * Failed responses are recorded to ErrorLog for ops (credential / quota decisions).
 */
final class PlatformHttp
{
    public static function client(?int $timeout = null): PendingRequest
    {
        $seconds = $timeout ?? (int) config('vtual.http.timeout', 20);

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
        $message = self::safeErrorMessage($response, $platformKey, $status);

        self::logFailure($message, $platformKey, $status, $url, $logContext, [
            'event' => 'platform_http_failed',
        ]);

        throw new PlatformApiException(
            message: $message,
            httpStatus: $status === 401 || $status === 403 ? 422 : 502,
            upstreamStatus: $status,
            platformKey: $platformKey,
        );
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
