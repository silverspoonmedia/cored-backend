<?php

use App\Support\ApiResponder;
use Illuminate\Auth\AuthenticationException;
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
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

return Application::configure(basePath: dirname(__DIR__))
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
        health: '/whatsup',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Laravel defaults to route('login') for guests; this API has no web login route.
        // Never redirect unauthenticated API clients—return JSON 401 instead.
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*')) {
                return null;
            }

            return null;
        });

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = $request->bearerToken()
                ? 'Token is invalid.'
                : 'Bearer token is required.';

            return ApiResponder::error($message, 401);
        });

        $exceptions->render(function (UnauthorizedHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = match ($e->getMessage()) {
                'Token not provided' => 'Bearer token is required.',
                'User not found' => 'Token is invalid.',
                default => $e->getMessage() ?: 'Could not authenticate token.',
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
                $e instanceof TokenExpiredException => 'Token has expired.',
                $e instanceof TokenInvalidException => 'Token is invalid.',
                $e instanceof TokenBlacklistedException => 'Token has been revoked.',
                default => 'Could not authenticate token.',
            };

            return ApiResponder::error($message, 401);
        });

        $exceptions->render(function (PermissionUnauthorizedException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponder::error('You are not authorized to perform this action.', 403);
        });
    })->create();
