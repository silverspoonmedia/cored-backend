<?php

use App\Domain\Identity\Http\Controllers\Web\LocaleController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web (Blade) frontend
|--------------------------------------------------------------------------
| The Blade frontend is a pure CONSUMER of the versioned API: every controller
| forwards to `/api/v1/*` (see App\Support\Api\ApiClient) and only renders the
| JSON it gets back. Business logic — validation, CRUD, RBAC — lives solely in
| the API, so these routes carry NO `permission:` gate on most paths: the API
| authorizes each forwarded call.
| Web middleware here is transport-only: `web.guest`/`web.auth`
| (who is logged in, where to redirect) and the JWT-cookie bridge that lets the
| browser carry the same stateless token the API issues.
|
| Domain route files live under routes/web/*.php (loaded below).
| Authenticated domain requires: core first, product last (see config/domains.php).
| Guest/public specials are auth-surface groups, not kind taxonomy.
*/

Route::get('/', fn () => redirect()->route(Auth::guard('api')->check() ? 'dashboard' : 'login'));

Route::get('locale/{locale}', LocaleController::class)->name('locale.switch');

// Public / provider return (no web.auth) — not ordered by domain kind.
require __DIR__.'/web/payment-ipaymu-return.php';
require __DIR__.'/web/file-hosting-public.php';

Route::middleware('web.guest')->group(function (): void {
    require __DIR__.'/web/identity-guest.php';
});

Route::middleware('web.auth')->group(function (): void {
    // Core domains
    require __DIR__.'/web/identity.php';
    require __DIR__.'/web/navigation.php';
    require __DIR__.'/web/notification.php';
    require __DIR__.'/web/activity-log.php';
    require __DIR__.'/web/payment-gateway.php';
    require __DIR__.'/web/service.php';

    // Product domains (domain:make injects into this group; keep product last)
    require __DIR__.'/web/file-hosting.php';
});
