<?php

namespace App\Support\Api;

use App\Support\Auth\AuthCookie;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
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
     * @param  array<string, mixed>  $query
     */
    public function get(string $uri, array $query = []): Response
    {
        return $this->pending()->get($this->url($uri), $query);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(string $uri, array $data = []): Response
    {
        return $this->pending()->post($this->url($uri), $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(string $uri, array $data = []): Response
    {
        return $this->pending()->put($this->url($uri), $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function delete(string $uri, array $data = []): Response
    {
        return $this->pending()->delete($this->url($uri), $data);
    }

    private function pending(): PendingRequest
    {
        $client = Http::acceptJson()->asJson();

        $token = request()?->cookie(AuthCookie::NAME);

        if (is_string($token) && $token !== '') {
            $client = $client->withToken($token);
        }

        return $client;
    }

    private function url(string $uri): string
    {
        // Call ourselves on the host the browser actually used, so this works
        // regardless of how APP_URL is configured behind proxies/vhosts.
        $base = request()?->getSchemeAndHttpHost() ?? rtrim((string) config('app.url'), '/');

        return $base.'/api/v1/'.ltrim($uri, '/');
    }
}
