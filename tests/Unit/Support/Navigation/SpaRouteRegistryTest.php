<?php

namespace Tests\Unit\Support\Navigation;

use App\Support\Navigation\SpaRouteRegistry;
use Tests\TestCase;

class SpaRouteRegistryTest extends TestCase
{
    public function test_menu_seeder_routes_are_registered(): void
    {
        $contents = (string) file_get_contents(database_path('seeders/MenuSeeder.php'));
        preg_match_all("/'route'\\s*=>\\s*'([^']+)'/", $contents, $matches);

        $this->assertNotEmpty($matches[1]);

        $allow = SpaRouteRegistry::menuRouteNames();

        foreach (array_unique($matches[1]) as $route) {
            $this->assertContains(
                $route,
                $allow,
                "MenuSeeder route [{$route}] is missing from config/spa-routes.php menu allowlist.",
            );
            $this->assertTrue(SpaRouteRegistry::isValidMenuRoute($route));
        }
    }

    public function test_product_menu_routes_are_registered(): void
    {
        /** @var array<string, array{menu_routes?: list<string>}> $products */
        $products = config('products', []);

        foreach ($products as $key => $product) {
            foreach ($product['menu_routes'] ?? [] as $route) {
                $this->assertTrue(
                    SpaRouteRegistry::isValidMenuRoute($route),
                    "products.php menu_routes [{$key} → {$route}] is not a valid SPA menu route.",
                );
            }
        }
    }

    public function test_denied_auth_and_utility_names_are_rejected(): void
    {
        foreach (['login', 'register', 'not-found', 'payment.return', 'public.file'] as $route) {
            $this->assertFalse(SpaRouteRegistry::isValidMenuRoute($route));
        }
    }

    public function test_empty_route_is_allowed(): void
    {
        $this->assertTrue(SpaRouteRegistry::isValidMenuRoute(null));
        $this->assertTrue(SpaRouteRegistry::isValidMenuRoute(''));
    }
}
