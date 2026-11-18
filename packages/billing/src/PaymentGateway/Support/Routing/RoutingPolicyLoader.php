<?php

namespace App\Domain\PaymentGateway\Support\Routing;

use Symfony\Component\Yaml\Yaml;

class RoutingPolicyLoader
{
    public function __construct(
        private RoutingPolicyValidator $validator,
    ) {}

    public function load(?string $path = null): RoutingPolicy
    {
        $path ??= (string) config('payment-routing.policy_path');

        if (! is_file($path)) {
            throw new \RuntimeException("Routing policy file not found: {$path}");
        }

        $parsed = Yaml::parseFile($path);

        if (! is_array($parsed)) {
            throw new \RuntimeException('Routing policy YAML must parse to an array.');
        }

        $this->validator->validateRoot($parsed);

        $channels = [];
        foreach ($parsed['channels'] as $key => $node) {
            if (! is_string($key) || ! is_array($node)) {
                continue;
            }

            $channels[$key] = ChannelPolicyDefinition::fromPolicyNode($key, $node);
        }

        return new RoutingPolicy($channels);
    }
}
