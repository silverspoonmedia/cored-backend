<?php

namespace App\Domain\PaymentGateway\Services;

use App\Domain\PaymentGateway\Models\PaymentChannel;
use App\Domain\PaymentGateway\Models\PaymentChannelRoute;
use App\Domain\PaymentGateway\Services\Payment\PaymentChannelResolver;
use Illuminate\Database\Eloquent\Collection;

class PaymentChannelService
{
    public function __construct(
        private PaymentChannelResolver $resolver,
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
            ->get();
    }

    /**
     * @return Collection<int, PaymentChannel>
     */
    public function listAvailable(): Collection
    {
        return $this->resolver->listAvailable();
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

        if (array_key_exists('priority', $data)) {
            $route->priority = (int) $data['priority'];
        }

        $route->save();

        return $route->fresh(['paymentGateway']);
    }
}
