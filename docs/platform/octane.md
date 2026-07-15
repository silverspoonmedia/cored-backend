# Octane / Swoole readiness

Octane is **not** installed yet. Every change must survive long-lived Swoole workers when it is.

## Why normal Laravel breaks

PHP-FPM process dies per request. Octane workers reuse memory — leaked state hits the next request.

| Category | Example |
|----------|---------|
| Guard/facade mutation | `auth()->claims()` / `setTTL` without reset |
| Static request caches | `static $results` |
| Stateful singletons | Request data on singleton |
| Runtime config mutation | `config()->set()` / `env()` at request time |
| Superglobal writes | Manual `$_COOKIE` etc. |

## Mandatory rules

### 1. No mutable guard state without cleanup

Prefer no custom JWT claims. If mutating TTL/guards:

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

### 2. No static properties for request data

Constants OK; static arrays of query/user results are not.

### 3. Singletons must be stateless

Request-aware services → `bind()` or `scoped()`, not `singleton()` with mutable fields.

### 4. No `env()` outside `config/`

Use `config('key')` at runtime.

### 5. No `config()->set()` during requests

Only providers (boot) or tests.

### 6–8. No superglobal writes, no manual output buffering, no blocking `sleep` on request path

### 9. Drivers for production Octane

| Component | Prefer for Octane |
|-----------|-------------------|
| Session | `database` / `redis` / `memcached` (not `file`) — already `database` |
| Cache / queue | Redis (or SQS for queue) in production — templates use `database` for local portability |

## When installing Octane later

1. `laravel/octane` + `ext-swoole`
2. `php artisan octane:install --server=swoole`
3. Review singletons; set `OCTANE_SERVER=swoole`
4. Production cache/queue → redis; tune DB pool
5. `php artisan octane:start`

Historical audit notes: [archive/](../archive/) if present. JWT remember-me uses TTL only (no sticky `claims` leak).

## Related

- [domains/identity/README.md](../domains/identity/README.md)
- [Laravel Octane](https://laravel.com/docs/octane)
