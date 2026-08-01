<?php

namespace Tests\Domain\PaymentGateway\Unit\Routing;

use App\Domain\PaymentGateway\Enums\PaymentRouteRole;
use App\Domain\PaymentGateway\Models\PaymentChannelRoute;
use App\Domain\PaymentGateway\Support\Routing\PaymentRouteSort;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PaymentRouteSortTest extends TestCase
{
    public function test_role_weight_orders_primary_before_fallback(): void
    {
        $this->assertSame(0, PaymentRouteSort::roleWeight(PaymentRouteRole::Primary));
        $this->assertSame(0, PaymentRouteSort::roleWeight(PaymentRouteRole::Only));
        $this->assertSame(1, PaymentRouteSort::roleWeight(PaymentRouteRole::Fallback));
        $this->assertSame(2, PaymentRouteSort::roleWeight(null));
    }

    public function test_sort_routes_by_role_then_priority(): void
    {
        $fallback = new PaymentChannelRoute([
            'route_role' => PaymentRouteRole::Fallback,
            'priority' => 10,
        ]);
        $primary = new PaymentChannelRoute([
            'route_role' => PaymentRouteRole::Primary,
            'priority' => 20,
        ]);
        $only = new PaymentChannelRoute([
            'route_role' => PaymentRouteRole::Only,
            'priority' => 99,
        ]);

        $sorted = PaymentRouteSort::sortRoutes(new Collection([$fallback, $primary, $only]));

        $this->assertSame(PaymentRouteRole::Primary, $sorted[0]->route_role);
        $this->assertSame(PaymentRouteRole::Only, $sorted[1]->route_role);
        $this->assertSame(PaymentRouteRole::Fallback, $sorted[2]->route_role);
    }

    public function test_sort_key_from_array_matches_role_and_priority(): void
    {
        $this->assertSame([0, 10], PaymentRouteSort::sortKeyFromArray([
            'route_role' => 'primary',
            'priority' => 10,
        ]));
        $this->assertSame([1, 5], PaymentRouteSort::sortKeyFromArray([
            'route_role' => 'fallback',
            'priority' => 5,
        ]));
    }
}
