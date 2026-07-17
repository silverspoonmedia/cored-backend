<?php

use App\Domain\ErrorLog\Support\ErrorLogRecorder;
use App\Domain\Identity\Models\User;
use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\AuthenticateWithJwtCookie;
use App\Http\Middleware\EnsureGatewayRequest;
use App\Http\Middleware\EnsureWebAuthenticated;
use App\Http\Middleware\OptionalJwtAuthenticate;
use App\Http\Middleware\RedirectIfWebAuthenticated;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Support\ApiResponder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenBlacklistedException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use Spatie\Permission\Exceptions\UnauthorizedException as PermissionUnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withEvents(discover: [
        __DIR__.'/../app/Domain/Identity/Listeners',
        __DIR__.'/../app/Domain/Notification/Listeners',
    ])
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
        __DIR__.'/../app/Domain/ErrorLog/Console/Commands',
        __DIR__.'/../app/Domain/Navigation/Console/Commands',
        __DIR__.'/../app/Domain/PaymentGateway/Console/Commands',
        __DIR__.'/../app/Domain/Service/Console/Commands',
    ])
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        then: function (): void {
            Route::middleware('api')
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
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Laravel defaults to route('login') for guests; this API has no web login route.
        // Never redirect unauthenticated API clients—return JSON 401 instead. Web guests
        // are redirected to login by the `web.auth` middleware, not here.
        $middleware->redirectGuestsTo(fn (Request $request) => null);

        // The Blade frontend authenticates with the same stateless JWT as the
        // API, carried in an HttpOnly cookie. Reading it on every web request
        // lets `permission:`/`role:` middleware and policies work unchanged.
        $middleware->web(append: [
            AuthenticateWithJwtCookie::class,
            SetLocale::class,
            SecurityHeaders::class,
        ]);

        $middleware->api(prepend: [
            EnsureGatewayRequest::class,
        ]);

        $middleware->api(append: [
            SetLocale::class,
        ]);

        // Resolve locale after auth middleware so users.locale wins over cookie.
        $middleware->priority([
            AuthenticateWithJwtCookie::class,
            OptionalJwtAuthenticate::class,
            Authenticate::class,
            SetLocale::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'jwt.optional' => OptionalJwtAuthenticate::class,
            'web.auth' => EnsureWebAuthenticated::class,
            'web.guest' => RedirectIfWebAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->dontReport([
            BusinessRuleException::class,
        ]);

        $exceptions->reportable(function (\Throwable $e): void {
            app(ErrorLogRecorder::class)->recordException($e);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $previous = $e->getPrevious();

            if ($previous instanceof ModelNotFoundException) {
                $message = match ($previous->getModel()) {
                    User::class => __('api/users.not_found'),
                    default => __('api/common.data_not_found'),
                };

                return ApiResponder::error($message, 404);
            }

            return ApiResponder::error(__('api/common.resource_not_found'), 404);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = $request->bearerToken()
                ? __('api/auth.token_invalid')
                : __('api/auth.bearer_required');

            return ApiResponder::error($message, 401);
        });

        $exceptions->render(function (UnauthorizedHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = match ($e->getMessage()) {
                'Token not provided' => __('api/auth.bearer_required'),
                'User not found' => __('api/auth.token_invalid'),
                default => $e->getMessage() ?: __('api/auth.could_not_authenticate'),
            };

            return ApiResponder::error($message, 401);
        });

        $exceptions->render(function (
            JWTException $e,
            Request $request,
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = match (true) {
                $e instanceof TokenExpiredException => __('api/auth.token_expired'),
                $e instanceof TokenInvalidException => __('api/auth.token_invalid'),
                $e instanceof TokenBlacklistedException => __('api/auth.token_revoked'),
                default => __('api/auth.could_not_authenticate'),
            };

            return ApiResponder::error($message, 401);
        });

        $exceptions->render(function (PermissionUnauthorizedException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponder::error(__('api/common.unauthorized'), 403);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponder::error(
                $e->getMessage() ?: __('api/common.unauthorized'),
                403,
            );
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponder::error(
                $e->getMessage() ?: __('api/common.unauthorized'),
                403,
            );
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
    })->create();

$app->singleton(\Illuminate\Contracts\Http\Kernel::class, \App\Http\Kernel::class);
$app->singleton(\Illuminate\Contracts\Console\Kernel::class, \App\Console\Kernel::class);

return $app;
