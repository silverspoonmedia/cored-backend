<?php

namespace App\Domain\Navigation\Support;

use Illuminate\Support\Facades\Route;

/**
 * Resolves registered route names from a navigation tree into URLs for the sidebar.
 */
final class NavigationRouteMap
{
    /**
     * @param  list<array<string, mixed>>  $tree
     * @return array<string, string>
     */
    public static function fromTree(array $tree): array
    {
        $map = [];
        self::walk($tree, $map);

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $tree
     * @param  array<string, string>  $map
     */
    private static function walk(array $tree, array &$map): void
    {
        foreach ($tree as $node) {
            $route = $node['route'] ?? null;

            if (is_string($route) && $route !== '' && Route::has($route)) {
                $map[$route] = route($route);
            }

            if (isset($node['children']) && is_array($node['children'])) {
                self::walk($node['children'], $map);
            }
        }
    }
}
