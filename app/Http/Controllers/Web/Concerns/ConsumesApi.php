<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Support\Api\ApiClient;
use Closure;
use Illuminate\Http\Client\Response;
use Illuminate\Http\RedirectResponse;

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
     * Translate a mutation response into a browser redirect.
     *
     * - 2xx        → the success closure (typically redirect with a flash)
     * - 422        → back with validation errors + old input (API is the validator)
     * - 401/419    → back to login (token expired/revoked)
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

        if (in_array($response->status(), [401, 419], true)) {
            return redirect()
                ->route('login')
                ->with('error', 'Sesi Anda telah berakhir. Silakan masuk kembali.');
        }

        return back()
            ->withInput()
            ->with('error', $this->apiMessage($response));
    }

    /**
     * The API envelope's `message`, with a safe fallback.
     */
    protected function apiMessage(Response $response, string $fallback = 'Permintaan gagal diproses.'): string
    {
        $message = $response->json('message');

        return is_string($message) && $message !== '' ? $message : $fallback;
    }
}
