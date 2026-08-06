<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Support\Api\ApiClient;
use Closure;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Shared glue for web controllers that are pure consumers of the versioned API.
 *
 * The controllers hold zero business logic: they forward to the API via
 * {@see ApiClient} and translate the JSON envelope back into the redirect /
 * flash / validation-error flow a browser expects. Validation, CRUD and RBAC
 * all live behind the API, so error shapes are mapped once here instead of
 * being re-implemented per controller.
 */
trait ConsumesApi
{
    protected function api(): ApiClient
    {
        return app(ApiClient::class);
    }

    /**
     * Strip framework form noise and optionally allowlist fields for API forward.
     *
     * Web controllers must not re-validate or reshape domain data — the API
     * FormRequest owns that. Prefer plain forward; use {@see $only} only as a
     * thin field allowlist (not business normalization).
     *
     * @param  list<string>|null  $only
     * @return array<string, mixed>
     */
    protected function apiPayload(Request $request, ?array $only = null): array
    {
        if ($only !== null) {
            return $request->only($only);
        }

        return $request->except(['_token', '_method']);
    }

    /**
     * Translate a mutation response for either a full-page redirect or an AJAX
     * form (`Accept: application/json` / `X-Requested-With: XMLHttpRequest`).
     *
     * @param  Closure(Response): RedirectResponse  $onSuccess
     * @param  bool  $stayOnPageOnSuccess  When true (AJAX only), success returns an
     *                                     inline message without `redirect`. Use for
     *                                     in-place actions (edit form save, send-link,
     *                                     resend verification). Leave false when the
     *                                     user should land on another screen (create,
     *                                     delete, login, logout).
     */
    protected function respondFromApi(
        Request $request,
        Response $response,
        Closure $onSuccess,
        ?string $jsonSuccessMessage = null,
        bool $stayOnPageOnSuccess = false,
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            return $this->jsonFromApi($request, $response, $onSuccess, $jsonSuccessMessage, $stayOnPageOnSuccess);
        }

        return $this->redirectFromApi($response, $onSuccess);
    }

    /**
     * JSON envelope for AJAX forms (no page reload on validation failure).
     *
     * @param  Closure(Response): RedirectResponse  $onSuccess
     */
    protected function jsonFromApi(
        Request $request,
        Response $response,
        Closure $onSuccess,
        ?string $successMessage = null,
        bool $stayOnPageOnSuccess = false,
    ): JsonResponse {
        if ($response->successful()) {
            $redirect = $onSuccess($response);
            $payload = [
                'status' => 'success',
                'message' => $successMessage ?? __('ui.ajax.saved'),
            ];

            // Forward API entity so SPA shells can soft-update without a second fetch.
            $apiData = $response->json('data');
            if ($apiData !== null) {
                $payload['data'] = $apiData;
            }

            if (! $stayOnPageOnSuccess) {
                $payload['redirect'] = $redirect->getTargetUrl();
            }

            return $this->withRedirectCookies(response()->json($payload), $redirect);
        }

        if ($response->status() === 422) {
            $payload = [
                'status' => 'error',
                'message' => $this->apiMessage($response, __('ui.ajax.validation_failed')),
                'errors' => $response->json('errors', []),
            ];

            $apiData = $response->json('data');
            if ($apiData !== null) {
                $payload['data'] = $apiData;
            }

            return response()->json($payload, 422);
        }

        // Auth death only. Web CSRF 419 is handled by the exception renderer
        // (`reload: true`); never map API 419 as "session expired" login kick.
        if ($response->status() === 401) {
            $message = __('web/session.expired');
            $request->session()->flash('error', $message);

            return response()->json([
                'status' => 'error',
                'message' => $message,
                'redirect' => route('login'),
            ], 401);
        }

        return response()->json([
            'status' => 'error',
            'message' => $this->apiMessage($response),
        ], $response->status() >= 400 ? $response->status() : 400);
    }

    /**
     * Translate a mutation response into a browser redirect.
     *
     * - 2xx        → the success closure (typically redirect with a flash)
     * - 422        → back with validation errors + old input (API is the validator)
     * - 401        → back to login (JWT expired/revoked — not Laravel session)
     * - other 4xx/5xx → back with the API's error message flashed
     *
     * @param  Closure(Response): RedirectResponse  $onSuccess
     */
    protected function redirectFromApi(Response $response, Closure $onSuccess): RedirectResponse
    {
        if ($response->successful()) {
            return $onSuccess($response);
        }

        if ($response->status() === 422) {
            return back()
                ->withInput()
                ->withErrors($response->json('errors', []));
        }

        if ($response->status() === 401) {
            return redirect()
                ->route('login')
                ->with('error', __('web/session.expired'));
        }

        return back()
            ->withInput()
            ->with('error', $this->apiMessage($response));
    }

    /**
     * The API envelope's `message`, with a safe fallback.
     */
    protected function apiMessage(Response $response, ?string $fallback = null): string
    {
        $message = $response->json('message');
        $fallback ??= __('ui.ajax.request_failed');

        return is_string($message) && $message !== '' ? $message : $fallback;
    }

    /**
     * Copy Set-Cookie headers from a redirect, and any cookies queued during the
     * request (e.g. IssuesAuthCookie / RefreshJwtCookie via cookie()->queue).
     */
    protected function withRedirectCookies(JsonResponse $json, RedirectResponse $redirect): JsonResponse
    {
        foreach ($redirect->headers->getCookies() as $cookie) {
            $json->headers->setCookie($cookie);
        }

        foreach (cookie()->getQueuedCookies() as $cookie) {
            $json->headers->setCookie($cookie);
        }

        return $json;
    }

    /**
     * JSON response for web-only actions that already built their redirect
     * (logout, etc.) without an API envelope.
     */
    protected function jsonFromRedirect(
        Request $request,
        RedirectResponse $redirect,
        ?string $message = null,
        bool $stayOnPageOnSuccess = false,
    ): JsonResponse {
        $payload = [
            'status' => 'success',
            'message' => $message,
        ];

        if (! $stayOnPageOnSuccess) {
            $payload['redirect'] = $redirect->getTargetUrl();
        }

        $json = response()->json(array_filter($payload, fn ($value) => $value !== null));

        return $this->withRedirectCookies($json, $redirect);
    }
}
