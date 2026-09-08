<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WebSurfaceRouteListTest extends TestCase
{
    /**
     * After Blade removal the non-API HTTP surface is server endpoints only.
     * `broadcasting/auth` is Laravel's default web registrar (SPA uses
     * `/api/v1/broadcasting/auth`). `whatsup` is the framework health path.
     *
     * @var list<string>
     */
    private const ALLOWED_URIS = [
        '/',
        'apps/auth/discord/callback',
        'broadcasting/auth',
        'payment/ipaymu/cancel',
        'payment/ipaymu/return',
        's/d/{folder}',
        's/f/{file}',
        's/f/{file}/download',
        'whatsup',
    ];

    /**
     * @var list<string>
     */
    private const FORBIDDEN_ROUTE_NAMES = [
        'login',
        'dashboard',
        'users.index',
        'archivd.index',
        'menus.index',
        'vtual.orphans.index',
    ];

    public function test_web_stack_only_exposes_server_endpoints(): void
    {
        $webUris = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if ($uri === 'storage/{path}' || str_starts_with($uri, 'api/') || str_starts_with($uri, '_ignition') || str_starts_with($uri, 'sanctum/')) {
                continue;
            }

            $webUris[] = $uri;
        }

        $webUris = array_values(array_unique($webUris));
        sort($webUris);

        $allowed = self::ALLOWED_URIS;
        sort($allowed);

        $this->assertSame(
            $allowed,
            $webUris,
            'Unexpected non-API web routes. Blade admin routes must not return.',
        );
    }

    public function test_blade_admin_route_names_are_not_registered(): void
    {
        foreach (self::FORBIDDEN_ROUTE_NAMES as $name) {
            $this->assertFalse(Route::has($name), "Blade route name [{$name}] must not be registered.");
        }
    }
}
