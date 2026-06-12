<?php

namespace App\Support\Api;

use App\Support\Auth\AuthCookie;
use App\Support\Localization\LocaleCookie;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP client the Blade frontend uses to call the application's OWN
 * versioned API (`/api/v1/*`). The web layer holds no business logic: it
 * forwards the request to the API — which is the single place validation, CRUD,
 * and RBAC run — and renders whatever JSON comes back.
 *
 * Authentication: the browser carries the stateless JWT in the HttpOnly
 * `auth_token` cookie. We read it and forward it as a `Bearer` header so the
 * API's `auth:api` guard authenticates the same actor — the web request never
 * touches the service layer directly.
 *
 * NOTE (concurrency): these are real HTTP round-trips to this same host, so the
 * web server MUST be able to serve a second concurrent request. Laragon
 * (Apache/Nginx + multiple PHP workers) does this out of the box; for
 * `php artisan serve` set `PHP_CLI_SERVER_WORKERS>=2` in `.env`, otherwise the
 * single worker deadlocks waiting on itself.
 */
class ApiClient
{
    /**
     * Path prefix every call is mounted under. Defaults to the versioned API
     * (`api/v1`) — where all production features live. The global playground
     * surface (`api/playground`) is reachable via {@see self::withPrefix()}.
     */
    private string $prefix = 'api/v1';

    /**
     * Return a clone targeting a different API prefix (e.g. `api/playground`).
     * Immutable so the shared singleton's default prefix is never mutated.
     */
    public function withPrefix(string $prefix): self
    {
        $clone = clone $this;
        $clone->prefix = trim($prefix, '/');

        return $clone;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $uri, array $query = []): Response
    {
        return $this->pending()->asJson()->get($this->url($uri), $query);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(string $uri, array $data = []): Response
    {
        return $this->pending()->asJson()->post($this->url($uri), $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(string $uri, array $data = []): Response
    {
        return $this->pending()->asJson()->put($this->url($uri), $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function delete(string $uri, array $data = []): Response
    {
        return $this->pending()->asJson()->delete($this->url($uri), $data);
    }

    /**
     * POST a multipart/form-data request (fields + uploaded files).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $files  keyed by field name (supports `field[]`)
     */
    public function postMultipart(string $uri, array $data = [], array $files = []): Response
    {
        return $this->multipart($files)->post($this->url($uri), $this->flatten($data));
    }

    /**
     * PUT with files. PHP does not parse multipart bodies on PUT, so this sends
     * a POST carrying `_method=PUT` (Laravel method spoofing) — the framework
     * routes it to the PUT handler while still populating `$request->file()`.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $files
     */
    public function putMultipart(string $uri, array $data = [], array $files = []): Response
    {
        return $this->multipart($files)->post($this->url($uri), array_merge($this->flatten($data), ['_method' => 'PUT']));
    }

    /**
     * Build a multipart pending request with the given files attached. `field`
     * names ending in `[]` (or repeated) become an array on the API side.
     *
     * @param  array<int, UploadedFile>  $files
     */
    private function multipart(array $files): PendingRequest
    {
        $client = $this->pending()->asMultipart();

        foreach ($files as $field => $file) {
            // Numeric keys → forward under `attachments[]` so the API sees an
            // array of uploaded files; string keys are forwarded as-is.
            $name = is_int($field) ? 'attachments[]' : $field;

            // Attach a stream resource, NOT $file->getContent(): the framework's
            // attach() runs the parts through array_filter(), which would drop an
            // empty-string body (a 0-byte file) and make Guzzle throw "A
            // 'contents' key is required". A resource is always truthy, so it
            // survives — and streaming avoids loading the whole file into memory.
            $client = $client->attach(
                $name,
                fopen($file->getRealPath(), 'r'),
                $file->getClientOriginalName(),
                ['Content-Type' => $file->getClientMimeType()],
            );
        }

        return $client;
    }

    /**
     * Flatten a nested array into multipart-safe bracketed keys, since
     * multipart/form-data cannot carry nested PHP arrays as values. E.g.
     * `['details' => [['product_name' => 'A']]]` becomes
     * `['details[0][product_name]' => 'A']`. Null values are dropped (a
     * multipart field cannot be null).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, scalar>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix.'['.$key.']';

            if (is_array($value)) {
                $flat += $this->flatten($value, $name);

                continue;
            }

            if ($value === null) {
                continue;
            }

            // Booleans must travel as 1/0 strings over multipart.
            $flat[$name] = is_bool($value) ? (int) $value : $value;
        }

        return $flat;
    }

    private function pending(): PendingRequest
    {
        // Only set Accept here, NOT asJson(): the JSON body format sticks the
        // `Content-Type: application/json` header on the request, which would
        // override the multipart boundary and leave the API with no uploaded
        // files. The plain get/post/put/delete helpers add asJson() themselves;
        // the multipart helpers add asMultipart().
        $client = Http::acceptJson();

        $token = request()?->cookie(AuthCookie::NAME);

        if (is_string($token) && $token !== '') {
            $client = $client->withToken($token);
        }

        // Keep web and API locale in sync. The web SetLocale already resolved
        // the active locale (db > cookie > Laravel default); forward it as the
        // locale cookie so the API's own SetLocale lands on the same value.
        // For authenticated users the API still resolves db first (identical
        // value); for guests / users without a DB preference this carries the
        // cookie the API would otherwise never receive on this server-to-server
        // call.
        $client = $client->withCookies(
            [LocaleCookie::name() => app()->getLocale()],
            request()?->getHost() ?? parse_url((string) config('app.url'), PHP_URL_HOST) ?? 'localhost',
        );

        return $client;
    }

    private function url(string $uri): string
    {
        // Call ourselves on the host the browser actually used, so this works
        // regardless of how APP_URL is configured behind proxies/vhosts.
        $base = request()?->getSchemeAndHttpHost() ?? rtrim((string) config('app.url'), '/');

        return $base.'/'.$this->prefix.'/'.ltrim($uri, '/');
    }
}
