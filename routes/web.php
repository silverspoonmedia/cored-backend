<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web surface (server-side only)
|--------------------------------------------------------------------------
| The Blade app frontend has been removed. The SPA (monolith-vue-workers)
| is the sole frontend and talks to the versioned API (/api/v1/*) using the
| cross-site __Host-spa_jwt cookie + VerifySpaCsrf double-submit nonce.
|
| What remains here are SERVER endpoints, not UI:
|  - `/`            -> redirect to the SPA origin
|  - public share   -> JSON/redirect for /s/f, /s/d guest links
|  - iPaymu return  -> provider browser return/cancel
|  - Discord OAuth  -> provider callback (state-carried identity)
|
| No Blade views are rendered anywhere below.
*/

Route::get('/', fn () => redirect()->to(config('services.spa.url') ?: '/'));

// Public / provider return (no auth) - server endpoints, not UI.
require __DIR__.'/web/payment-ipaymu-return.php';
require __DIR__.'/web/identity-oauth-callback.php';
require __DIR__.'/web/archivd-public.php';

// Product domain web routes (optional server endpoints; appended by domain:make).






























