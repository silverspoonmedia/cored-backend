<?php

namespace App\Domain\PaymentGateway\Services;

use App\Domain\PaymentGateway\Models\PaymentChannel;
use App\Domain\PaymentGateway\Models\PaymentChannelRoute;
use App\Domain\PaymentGateway\Services\Payment\InvoiceChannelFeeService;
use App\Domain\PaymentGateway\Services\Payment\PaymentChannelResolver;
use App\Domain\PaymentGateway\Support\PaymentRealtime;
use App\Domain\PaymentGateway\Support\Routing\PaymentRouteSort;
use Illuminate\Database\Eloquent\Collection;

class PaymentChannelService
{
    public function __construct(
        private PaymentChannelResolver $resolver,
        private InvoiceChannelFeeService $channelFees,
        private PaymentRealtime $paymentRealtime,
    ) {}

    /**
     * @return Collection<int, PaymentChannel>
     */
    public function listAll(): Collection
    {
        return PaymentChannel::query()
            ->with(['routes.paymentGateway'])
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get()
            ->each(function (PaymentChannel $channel): void {
                $channel->setRelation(
                    'routes',
                    PaymentRouteSort::sortRoutes($channel->routes),
                );
            });
    }

    /**
     * @return Collection<int, PaymentChannel>
     */
    public function listAvailable(?float $subtotal = null): Collection
    {
        $channels = $this->resolver->listAvailable();

        if ($subtotal === null) {
            return $channels;
        }

        return $channels->each(function (PaymentChannel $channel) use ($subtotal): void {
            $quote = $this->channelFees->quoteForChannel($channel, $subtotal);

            if ($quote !== null) {
                $channel->setAttribute('checkout_quote', $quote);
            }
        });
    }

    public function getByCode(string $code): ?PaymentChannel
    {
        $channel = PaymentChannel::query()->where('code', $code)->first();

        if ($channel === null || ! $channel->is_active) {
            return null;
        }

        if ($this->resolver->resolveRouteForChannel($channel) === null) {
            return null;
        }

        return $channel;
    }

    /**
     * @param  array{is_active?: bool}  $data
     */
    public function update(string $code, array $data): PaymentChannel
    {
        $channel = PaymentChannel::query()->where('code', $code)->firstOrFail();

        if (array_key_exists('is_active', $data)) {
            $channel->is_active = (bool) $data['is_active'];
        }

        $channel->save();

        $this->paymentRealtime->routingCatalogChanged('channel_updated', $channel->code);

        return $channel;
    }

    /**
     * @param  array{is_active?: bool, priority?: int}  $data
     */
    public function updateRoute(string $routeId, array $data): PaymentChannelRoute
    {
        $route = PaymentChannelRoute::query()
            ->with('paymentGateway')
            ->findOrFail($routeId);

        if (array_key_exists('is_active', $data)) {
            $route->is_active = (bool) $data['is_active'];
        }

        if (array_key_exists('priority', $data) && ! $route->is_policy_managed) {
            $route->priority = (int) $data['priority'];
        }

        $route->save();

        $route->loadMissing('paymentChannel');
        $this->paymentRealtime->routingCatalogChanged(
            'route_updated',
            $route->paymentChannel?->code,
        );

        return $route->fresh(['paymentGateway']);
    }
}
