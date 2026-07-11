<?php

namespace App\Domain\PaymentGateway\Services\Payment;

use App\Domain\PaymentGateway\Models\PaymentChannel;
use App\Domain\PaymentGateway\Models\PaymentChannelRoute;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use Illuminate\Database\Eloquent\Collection;

class PaymentChannelResolver
{
    public function __construct(
        private PaymentGatewayCredentialChecker $credentialChecker,
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
        $viable = new Collection;

        $routes = $channel->routes()
            ->where('is_active', true)
            ->with('paymentGateway')
            ->orderBy('priority')
            ->get();

        foreach ($routes as $route) {
            $gateway = $route->paymentGateway;

            if ($gateway === null || ! $gateway->is_active) {
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
}
