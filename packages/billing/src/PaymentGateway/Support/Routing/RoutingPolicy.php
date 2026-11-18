<?php

namespace App\Domain\PaymentGateway\Support\Routing;

readonly class RoutingPolicy
{
    /**
     * @param  array<string, ChannelPolicyDefinition>  $channels
     */
    public function __construct(
        public array $channels,
    ) {}

    public function channel(string $key): ?ChannelPolicyDefinition
    {
        return $this->channels[$key] ?? null;
    }

    public function vaPolicy(): ?ChannelPolicyDefinition
    {
        return $this->channel('va');
    }

    public function qrisPolicy(): ?ChannelPolicyDefinition
    {
        return $this->channel('qris');
    }
}
