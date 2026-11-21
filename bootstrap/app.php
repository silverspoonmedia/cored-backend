<?php

use App\Console\Commands\Domain\ListDomainsCommand;
use App\Console\Commands\EncryptionFlushDecryptedCacheCommand;
use App\Exceptions\BusinessRuleException;
use App\Http\Kernel;
use App\Http\Middleware\AuthenticateWithSpaCookie;
use App\Http\Middleware\OptionalJwtAuthenticate;
use App\Http\Middleware\RequireSpaOrigin;
use App\Http\Middleware\RequireSpaRequestHeader;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\VerifySpaCsrf;
use App\Support\Auth\SpaCsrf;
use App\Support\ErrorLogging\ExceptionRecorder;
use App\Support\Http\ApiExceptionEnvelope;
use App\Support\Http\StampApiCors;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withEvents(discover: [
        __DIR__.'/../packages/platform/src/Domain/Identity/Listeners',
    ])
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
        __DIR__.'/../app/Domain/Ai/Console/Commands',
        __DIR__.'/../app/Domain/Archivd/Console/Commands',
        __DIR__.'/../app/Domain/Microstock/Console/Commands',
        // Kernel-owned: both read state and edit nothing, so a consumer install
        // runs the same classes from vendor. The directory above carries no
        // copy of either.
        ListDomainsCommand::class,
        EncryptionFlushDecryptedCacheCommand::class,
    ])
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        then: function (): void {
            // Global API throttle: browser tabs hit the API directly, so
            // per-user/IP limits keep a single abusive session from starving
            // the rest. Stricter per-route throttles (login, 2FA, batch)
            // still win where defined.
            RateLimiter::for('api', fn (Request $request) => [
                Limit::perMinute(60)->by($request->user()?->getAuthIdentifier() ?: $request->ip()),
            ]);

            // Dedicated broadcast-auth throttle: when Echo connects it authorizes
            // every subscribed channel (one POST /broadcasting/auth each), which
            // together with regular traffic can exhaust the global 60/min limiter
            // and fail channel auth with 429. Higher per-user limit still caps
            // abuse but leaves headroom for a multi-channel connect burst.
            RateLimiter::for('broadcast', fn (Request $request) => [
                Limit::perMinute(120)->by($request->user()?->getAuthIdentifier() ?: $request->ip()),
            ]);

            Route::middleware(['api', 'throttle:api'])
                ->prefix('api/v1')
                ->name('api.v1.')
                ->group(base_path('routes/api/v1.php'));

            // Route::middleware('api')
            //     ->prefix('api/v2')
            //     ->name('api.v2.')
            //     ->group(base_path('routes/api/v2.php'));
        },
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/whatsup',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cloudflare Tunnel (and other local reverse proxies) forward X-Forwarded-*
        // headers so route()/redirect() use the public host, not the Laragon vhost.
        // The chain stays wildcard-trusted; RequireSpaOrigin + rate limits below
        // treat $request->ip() (the outermost Cloudflare client IP) as the
        // identity, which is what audit and throttling need.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Laravel defaults to route('login') for guests; this API has no web login route.
        // Never redirect unauthenticated API clients — return JSON 401 instead. Login
        // lives on the SPA origin.
        $middleware->redirectGuestsTo(fn (Request $request) => null);

        // The Blade app is gone; the only remaining web routes are server-side
        // endpoints (guest share, iPaymu return, Discord OAuth callback). No JWT
        // cookie bridge runs here any more - locale + security headers only.
        $middleware->web(append: [
            SetLocale::class,
            SecurityHeaders::class,
        ]);

        $middleware->api(prepend: [
            // Decrypt the SPA's cross-site cookies (auth + CSRF nonce) before the
            // auth/CSRF middleware read them. The api group has no EncryptCookies
            // by default; Laravel encrypts __Host-spa_jwt the same way as other cookies.
            EncryptCookies::class,
            RequireSpaRequestHeader::class,
        ]);

        // The SPA's double-submit CSRF nonce must stay readable by JS, so it is
        // excluded from encryption (both directions). It is a random, privilege-free
        // value; plaintext is safe by design. The auth cookie (__Host-spa_jwt) stays
        // encrypted — only the API ever reads it.
        $middleware->encryptCookies(except: [
            SpaCsrf::COOKIE_NAME,
        ]);

        $middleware->api(append: [
            RequireSpaOrigin::class,
            // Bind the SPA's cross-site HttpOnly JWT cookie to the api guard so
            // direct (Worker-less) data requests authenticate, then enforce the
            // double-submit CSRF token on cookie-authenticated mutations.
            AuthenticateWithSpaCookie::class,
            VerifySpaCsrf::class,
            SetLocale::class,
        ]);

        // Resolve locale after auth middleware so users.locale wins over cookie.
        $middleware->priority([
            OptionalJwtAuthenticate::class,
            Authenticate::class,
            SetLocale::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'jwt.optional' => OptionalJwtAuthenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The envelope itself is kernel policy (`App\Support\Http\ApiExceptionEnvelope`),
        // shared with a consumer that installs `silverspoonmedia/platform` alone.
        // Host-only concerns stay below: reporting, the 419 session branch, CORS.
        ApiExceptionEnvelope::register($exceptions->handler);

        $exceptions->dontReport([
            BusinessRuleException::class,
        ]);

        $exceptions->reportable(function (Throwable $e): void {
            // Kernel-owned contract: the error-log domain binds it when the
            // `silverspoonmedia/audit` package is installed. Without it, the report
            // still goes to the file log through the default handler.
            if (app()->bound(ExceptionRecorder::class)) {
                app(ExceptionRecorder::class)->recordException($e);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*') || $e->getStatusCode() !== 419) {
                return null;
            }

            if (! $e->getPrevious() instanceof TokenMismatchException) {
                return null;
            }

            $message = __('web/session.csrf_expired');
            $request->session()->flash('error', $message);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                    'reload' => true,
                ], 419);
            }

            return back()
                ->withInput()
                ->with('error', $message);
        });

        // Exception / abort() responses skip HandleCors's $next wrap. Stamp the
        // same credentialed allowlist so the SPA reads 401/403/419 instead of a
        // browser CORS TypeError. No-ops when HandleCors already set the header.
        $exceptions->respond(function (mixed $response, mixed $exception = null, mixed $request = null) {
            if (! $response instanceof SymfonyResponse) {
                return $response;
            }

            $req = $request instanceof Request ? $request : request();
            if (! $req instanceof Request) {
                return $response;
            }

            return StampApiCors::apply($response, $req);
        });
    })->create();

$app->singleton(Illuminate\Contracts\Http\Kernel::class, Kernel::class);
$app->singleton(Illuminate\Contracts\Console\Kernel::class, App\Console\Kernel::class);

return $app;
