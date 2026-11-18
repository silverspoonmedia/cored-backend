<?php

namespace App\Domain\PaymentGateway\Support\Routing;

class RoutingPolicyValidator
{
    private const ALLOWED_GATEWAYS = ['midtrans', 'ipaymu'];

    /**
     * @param  array<string, mixed>  $root
     */
    public function validateRoot(array $root): void
    {
        if (! isset($root['channels']) || ! is_array($root['channels'])) {
            throw new \InvalidArgumentException('Routing policy requires channels map.');
        }

        foreach ($root['channels'] as $key => $node) {
            if (! is_string($key) || ! is_array($node)) {
                throw new \InvalidArgumentException('Each channel entry must be a keyed array.');
            }

            $this->validateChannel($key, $node);
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function validateChannel(string $key, array $node): void
    {
        $type = $node['type'] ?? null;
        if (! in_array($type, ['single', 'dual'], true)) {
            throw new \InvalidArgumentException("Channel {$key} requires type single or dual.");
        }

        if ($key === 'linkaja') {
            $this->validateFee($node['fee'] ?? null, "{$key}.fee");

            return;
        }

        if ($type === 'single') {
            $this->validateRouteNode($node, "{$key}");
            if (isset($node['cheap_alt']) && ! is_string($node['cheap_alt'])) {
                throw new \InvalidArgumentException("Channel {$key} cheap_alt must be a string.");
            }

            return;
        }

        if (isset($node['primary']) && is_array($node['primary'])) {
            $this->validateRouteNode($node['primary'], "{$key}.primary");
        }

        if (isset($node['fallback']) && is_array($node['fallback'])) {
            $this->validateRouteNode($node['fallback'], "{$key}.fallback");
        }

        if (! isset($node['primary']) && ! isset($node['fallback'])) {
            throw new \InvalidArgumentException("Dual channel {$key} requires primary or fallback.");
        }

        if (isset($node['single_overrides']) && ! is_array($node['single_overrides'])) {
            throw new \InvalidArgumentException("Channel {$key} single_overrides must be a map.");
        }

        if (isset($node['avoid']) && ! is_array($node['avoid'])) {
            throw new \InvalidArgumentException("Channel {$key} avoid must be a list.");
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function validateRouteNode(array $node, string $path): void
    {
        $pg = $node['pg'] ?? null;
        if (! is_string($pg) || ! in_array(strtolower($pg), self::ALLOWED_GATEWAYS, true)) {
            throw new \InvalidArgumentException("{$path}.pg must be midtrans or ipaymu.");
        }

        $settlement = $node['settlement'] ?? null;
        if (! is_string($settlement) || ! preg_match('/^H\+\d+$/i', $settlement)) {
            throw new \InvalidArgumentException("{$path}.settlement must be like H+2.");
        }

        $this->validateFee($node['fee'] ?? null, "{$path}.fee");
    }

    private function validateFee(mixed $fee, string $path): void
    {
        if ($fee === null) {
            throw new \InvalidArgumentException("{$path} is required.");
        }

        FeePolicyDefinition::fromMixed($fee);
    }
}
