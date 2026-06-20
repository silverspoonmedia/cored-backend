# Octane / Swoole Readiness

This guide documents the rules and patterns required to keep `Monolith` runtime-safe under Laravel Octane with Swoole (long-lived, shared-memory workers). Octane is not installed yet; this document exists so that every future change is written to survive when Octane is adopted.

## Why Octane breaks "normal" Laravel code

Under PHP-FPM a fresh process handles each request and dies immediately after. Under Octane/Swoole the worker process lives across thousands of requests. Anything written to process-level state without cleanup becomes visible to the next request. The main categories of bugs:

| Category | Example |
|----------|---------|
| Facade/guard state mutation | `auth()->claims([...])` or `auth()->setTTL(...)` without reset |
| Static property caches | Storing request/user/query results on `static $foo` |
| Singleton state leak | Singleton service that holds request-scoped data |
| Superglobal mutation | Writing to `$_SERVER`, `$_COOKIE`, etc. |
| Runtime config mutation | `config()->set(...)` or `env(...)` at request time |
| Output buffer state | `ob_start()` / `ob_get_clean()` without matching cleanup |

## Rules (mandatory for all new code)

### 1. No mutable facade / guard state without cleanup

**Problem:** JWT package stores custom claims on a singleton factory. Under Octane, calling `$guard->claims([...])` once contaminates every subsequent token generation in that worker until the factory is destroyed.

**Solution:** Avoid `claims()` entirely if possible. If custom claims are necessary, clear the singleton factory after each token operation:

```php
try {
    $guard->setTTL(120);
    return $guard->attempt($credentials);
} finally {
    Auth::forgetGuards();
    app()->forgetInstance('tymon.jwt');
    app()->forgetInstance('tymon.jwt.manager');
}
```

**Better:** Store token metadata (e.g., "remember me" flag) outside the JWT payload — in the response envelope or client storage — and avoid mutable claims altogether.

### 2. No static properties storing request data

Never cache request, user, session, query result, or any variable-length data on a static property. Static class-level constants are fine; static arrays/collections/objects are traps.

```php
// BAD — leaks across workers
class BadCache {
    private static array $results = [];

    public static function get(string $key): mixed {
        return self::$results[$key] ?? null;
    }
}

// OK — only caches compiled config (immutable at runtime)
class GoodConfig {
    private static ?array $methods = null;

    public static function list(): array {
        return self::$methods ??= config('some.static.list');
    }
}
```

### 3. Singletons must be stateless

Any class bound via `app()->singleton()` or `$this->app->singleton()` must hold zero mutable state scoped to a request, user, or session. Prefer `bind()` (transient) or `scoped()` (per-request singleton reset) for request-aware services.

Example — safe singleton (stateless factory):

```php
// OK: receives all data via method parameters, stores nothing
final class DataTableResponseFactory {
    public function respond(iterable $items, callable $presentRow, ...): JsonResponse {
        // ...
    }
}
```

If a singleton needs request data, convert it to `bind()`.

### 4. No `env()` outside config files

Laravel caches config on worker boot. Calling `env()` at runtime reads the environment but bypasses config caching. Use `config('key')` everywhere except in files under `config/`.

### 5. No `config()->set()` during requests

Runtime config mutations persist across requests in Octane. Use `config()->set()` only in service providers (boot time) or tests (reset per test).

### 6. No superglobal read/write outside middleware

`$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE`, `$_SESSION`, `$_FILES`, `$_REQUEST` must not be written to directly. Swoole manages these per-coroutine — manual writes cause corruption.

### 7. No manual output buffering

Swoole handles response output. `ob_start()`, `ob_get_clean()`, and friends can interfere with Swoole's output pipeline.

### 8. No blocking calls in request path

`sleep()`, `usleep()`, `time_nanosleep()` — Swoole workers are cooperative. Blocking the entire worker blocks all concurrent coroutines.

### 9. Database / cache / session driver requirements

| Component | Requirement for Octane | Status (2026-06-20) |
|-----------|----------------------|---------------------|
| Session driver | `database`, `redis`, or `memcached` (NOT `file`) | Already `database` |
| Cache driver | `database` is safe but can bottleneck under high concurrency; prefer `redis` or `memcached` for production Octane | `.env.example` uses `database` for dev portability |
| Queue | `database` is safe but can bottleneck workers; prefer `redis` or `sqs` for production Octane | `.env.example` uses `database` for dev portability |
| Database | Tune max connections/pool limits before enabling Swoole concurrency | Common setup |

`database` cache and queue drivers will not explode at runtime, but they will turn concurrent Swoole workers into database traffic spam. Safe is not the same as fast. For production Octane, plan Redis for cache, locks, queues, and broadcasting fan-out where applicable.

## Audit history

| Date | Scope | Findings | Status |
|------|-------|----------|--------|
| 2026-06-20 | Initial full audit: controllers, services, views, providers | 1 finding: JWT singleton state persists custom claims across requests under Octane | Fixed. Removed `rm` claim entirely; `remember_me` now affects TTL only. `Auth::forgetGuards()` added to cleanup path. Regression test added. |

## Adding new code after Octane is installed

When Octane + Swoole are installed:

1. Add `laravel/octane` and `ext-swoole` to `composer.json`.
2. Run `php artisan octane:install --server=swoole`.
3. Register Octane-specific service providers if needed.
4. Review every singleton binding for statelessness.
5. Set `OCTANE_SERVER=swoole` in `.env`.
6. Switch production cache and queue drivers to `redis` (or queue to `sqs`).
7. Tune database connection limits for Swoole concurrency.
8. Run `php artisan octane:start` instead of `php artisan serve`.

Until then, follow the rules above and every PR is automatically Octane-ready.

## Related

- [Laravel Octane documentation](https://laravel.com/docs/octane)
- [Swoole coroutine limitations](https://openswoole.com/docs/get-started/coroutine)
- [JWT Authentication & RBAC](jwt-authentication.md) — JWT guard usage
- [README](../README.md) — Project overview