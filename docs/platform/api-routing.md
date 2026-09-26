# API Routing

Two **independent** route layers: **global** (unversioned) and **versioned**. They do not evolve into each other.

`/api/*` stays a small stable surface — liveness only (`GET /api/ping`). Every piece of product business logic lives on `/api/v{N}/*`, registered from one loader per version (`routes/api/v{N}.php`) that requires the domain files underneath it. Do not mirror an endpoint on both layers, and never break a contract in place: a breaking change ships as a new version with new controllers under `App\Domain\{Domain}\Http\Controllers\API\V{N}` and the `api.v{N}.` route-name prefix. Within a loader, core domain requires come before product ones.

| | |
|---|---|
| Code | `App\Domain\{Domain}\Http\Controllers\API\V{N}`, `App\Http\Controllers\API\PingController` |
| Routes | `routes/api.php`, `routes/api/v1.php`, `routes/api/v1/*.php`, `routes/web/*.php` |
| Config | `bootstrap/app.php` (`withRouting`), `config/cors.php` |
| Commands | `php artisan route:list --path=api` |
| Tests | `tests/Feature/Http/CorsBroadcastAuthTest.php` |
| Pakem | [api-routing.mdc](../../.cursor/rules/api-routing.mdc) · [api-resources.mdc](../../.cursor/rules/api-resources.mdc) |
| Deep dive | [reference-api-routing.md](reference-api-routing.md) |
