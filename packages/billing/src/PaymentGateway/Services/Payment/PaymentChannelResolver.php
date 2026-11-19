<?php

namespace App\Domain\PaymentGateway\Services\Payment;

use App\Domain\PaymentGateway\Models\PaymentChannel;
use App\Domain\PaymentGateway\Models\PaymentChannelRoute;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Support\Routing\ChannelPolicyDefinition;
use App\Domain\PaymentGateway\Support\Routing\PaymentRouteSort;
use App\Domain\PaymentGateway\Support\Routing\RoutingPolicy;
use App\Domain\PaymentGateway\Support\Routing\RoutingPolicyLoader;
use Illuminate\Database\Eloquent\Collection;

class PaymentChannelResolver
{
    private ?RoutingPolicy $policy = null;

    public function __construct(
        private PaymentGatewayCredentialChecker $credentialChecker,
        private RoutingPolicyLoader $policyLoader,
    ) {}

    /**
     * @return array{channel: PaymentChannel, route: PaymentChannelRoute, gateway: PaymentGateway}|null
     */
    public function resolve(string $channelCode): ?array
    {
        $channel = PaymentChannel::query()
            ->where('code', $channelCode)
            ->where('is_active', true)
            ->first();

        if ($channel === null) {
            return null;
        }

        $route = $this->resolveRouteForChannel($channel);

        if ($route === null) {
            return null;
        }

        $route->loadMissing('paymentGateway');

        return [
            'channel' => $channel,
            'route' => $route,
            'gateway' => $route->paymentGateway,
        ];
    }

    /**
     * @return Collection<int, PaymentChannel>
     */
    public function listAvailable(): Collection
    {
        return PaymentChannel::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get()
            ->filter(fn (PaymentChannel $channel): bool => $this->listViableRoutes($channel)->isNotEmpty())
            ->values();
    }

    /**
     * @return Collection<int, PaymentChannelRoute>
     */
    public function listViableRoutes(PaymentChannel $channel): Collection
    {
        $policy = $this->channelPolicy($channel);
        $viable = new Collection;

        $routes = $channel->routes()
            ->where('is_active', true)
            ->with('paymentGateway')
            ->get()
            ->sortBy(fn (PaymentChannelRoute $route, int $index): array => [
                PaymentRouteSort::roleWeight($route->route_role),
                $route->priority,
            ])
            ->values();

        foreach ($routes as $route) {
            $gateway = $route->paymentGateway;

            if ($gateway === null || ! $gateway->is_active) {
                continue;
            }

            if ($policy !== null && $policy->isAvoidedTag($route->policy_tag)) {
                continue;
            }

            if (! $this->credentialChecker->isConfigured($gateway)) {
                continue;
            }

            $viable->push($route);
        }

        return $viable;
    }

    public function resolveRouteForChannel(PaymentChannel $channel): ?PaymentChannelRoute
    {
        return $this->listViableRoutes($channel)->first();
    }

    private function channelPolicy(PaymentChannel $channel): ?ChannelPolicyDefinition
    {
        $key = $channel->policy_key ?? $channel->code;

        if (str_starts_with($channel->code, 'va_')) {
            $key = 'va';
        }

        return $this->routingPolicy()->channel($key);
    }

    private function routingPolicy(): RoutingPolicy
    {
        return $this->policy ??= $this->policyLoader->load();
    }
}
