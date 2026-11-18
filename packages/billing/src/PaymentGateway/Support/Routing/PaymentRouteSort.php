<?php

namespace App\Domain\PaymentGateway\Support\Routing;

use App\Domain\PaymentGateway\Enums\PaymentRouteRole;
use App\Domain\PaymentGateway\Models\PaymentChannelRoute;
use Illuminate\Support\Collection;

class PaymentRouteSort
{
    public static function roleWeight(PaymentRouteRole|string|null $role): int
    {
        $value = $role instanceof PaymentRouteRole ? $role->value : (string) $role;

        return match ($value) {
            PaymentRouteRole::Only->value, PaymentRouteRole::Primary->value => 0,
            PaymentRouteRole::Fallback->value => 1,
            default => 2,
        };
    }

    /**
     * @param  Collection<int, PaymentChannelRoute>  $routes
     * @return Collection<int, PaymentChannelRoute>
     */
    public static function sortRoutes(Collection $routes): Collection
    {
        return $routes
            ->sortBy(fn (PaymentChannelRoute $route): array => [
                self::roleWeight($route->route_role),
                $route->priority,
            ])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $route
     * @return array{0: int, 1: int}
     */
    public static function sortKeyFromArray(array $route): array
    {
        return [
            self::roleWeight(data_get($route, 'route_role')),
            (int) data_get($route, 'priority', 9999),
        ];
    }
}
