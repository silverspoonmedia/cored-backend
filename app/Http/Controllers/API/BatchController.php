<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Dispatches several read-only API calls in one HTTP request so the SPA BFF
 * (Cloudflare Worker) spends one invocation per hydrate instead of one per
 * widget. GET/HEAD only — mutations must stay one-request-per-action so
 * throttles, audit logs, and idempotency semantics keep working.
 *
 * Every sub-request is re-dispatched through the HTTP kernel, so each target
 * route keeps its own auth and permission middleware.
 */
class BatchController extends Controller
{
    private const MAX_REQUESTS = 10;

    private const ALLOWED_METHODS = ['GET', 'HEAD'];

    public function __invoke(Request $request): JsonResponse
    {
        $payloads = $request->input('requests');

        if (! is_array($payloads) || $payloads === []) {
            return ApiResponder::error(__('api/common.batch_invalid'), 422);
        }

        if (count($payloads) > self::MAX_REQUESTS) {
            return ApiResponder::error(__('api/common.batch_too_many'), 422);
        }

        $responses = [];

        foreach ($payloads as $index => $payload) {
            $responses[] = $this->dispatchSubRequest($request, $payload, (int) $index);
        }

        return ApiResponder::success($responses, __('api/common.batch_ok'));
    }

    /**
     * @param  mixed  $payload  Untrusted sub-request description.
     * @return array{status: int, body: mixed}
     */
    private function dispatchSubRequest(Request $parent, mixed $payload, int $index): array
    {
        if (! is_array($payload)) {
            return $this->subError(422, __('api/common.batch_entry_invalid'));
        }

        $method = strtoupper((string) ($payload['method'] ?? ''));
        $path = (string) ($payload['path'] ?? '');

        if (! in_array($method, self::ALLOWED_METHODS, true)) {
            return $this->subError(422, __('api/common.batch_method_not_allowed'));
        }

        // Same-origin only: sub-requests never leave this API version.
        $path = '/'.ltrim($path, '/');
        if (! str_starts_with($path, '/api/v1/') && $path !== '/api/v1') {
            return $this->subError(422, __('api/common.batch_path_not_allowed'));
        }

        $sub = Request::create($path, $method, server: $parent->server->all());

        // Forward the caller identity — never forward Cookie (Blade) so the
        // batch cannot smuggle a second session into an API-only request.
        foreach (['authorization', 'accept', 'accept-language', 'x-requested-with', 'x-spa-origin', 'x-spa-client-ip', 'x-forwarded-for', 'x-forwarded-proto'] as $header) {
            $value = $parent->headers->get($header);
            if ($value !== null && $value !== '') {
                $sub->headers->set($header, $value);
            }
        }

        try {
            $response = App::handle($sub);
        } catch (\Throwable $e) {
            report($e);

            return $this->subError(500, __('api/common.batch_sub_failed'));
        }

        return $this->wrapSubResponse($response);
    }

    /**
     * @return array{status: int, body: mixed}
     */
    private function wrapSubResponse(SymfonyResponse $response): array
    {
        $body = $response->getContent();
        $decoded = is_string($body) ? json_decode($body, true) : null;

        return [
            'status' => $response->getStatusCode(),
            'body' => $decoded ?? $body,
        ];
    }

    /**
     * @return array{status: int, body: array{status: string, message: string}}
     */
    private function subError(int $status, string $message): array
    {
        Log::debug('Batch sub-request rejected', ['status' => $status, 'reason' => $message]);

        return [
            'status' => $status,
            'body' => ['status' => 'error', 'message' => $message],
        ];
    }
}
