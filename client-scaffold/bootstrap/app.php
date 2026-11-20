<?php

use App\Http\Middleware\AuthenticateWithJwtCookie;
use App\Http\Middleware\EnsureWebAuthenticated;
use App\Http\Middleware\OptionalJwtAuthenticate;
use App\Http\Middleware\RedirectIfWebAuthenticated;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        then: function (): void {
            // The version loader mounts every core package endpoint (registry
            // order) and then the client's own routes. Packages are never
            // required one by one here: config/domains.php names them and
            // DomainManager resolves each install path through Composer.
            Route::middleware('api')
                ->prefix('api/v1')
                ->name('api.v1.')
                ->group(base_path('routes/api/v1.php'));

            if (file_exists(base_path('routes/api/v2.php'))) {
                Route::middleware('api')
                    ->prefix('api/v2')
                    ->name('api.v2.')
                    ->group(base_path('routes/api/v2.php'));
            }
        },
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/whatsup',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (Request $request) => null);

        $middleware->web(append: [
            AuthenticateWithJwtCookie::class,
            SetLocale::class,
            SecurityHeaders::class,
        ]);

        $middleware->api(append: [
            SetLocale::class,
        ]);

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
        // The API error envelope is kernel policy: `App\Support\Http\ApiExceptionEnvelope`
        // turns a 401/403/404/429 into `{status: "error", message: …}`. This project
        // registers nothing for that — `KernelServiceProvider` already does, and
        // registering twice would answer every exception with two renderables.
        // Host-only concerns belong below.

        $exceptions->dontReport([
            AuthorizationException::class,
            AuthenticationException::class,
            ModelNotFoundException::class,
            NotFoundHttpException::class,
            PermissionUnauthorizedException::class,
            AccessDeniedHttpException::class,
            UnauthorizedHttpException::class,
            JWTException::class,
            TokenExpiredException::class,
            TokenInvalidException::class,
            TokenBlacklistedException::class,
        ]);
    })->create();
