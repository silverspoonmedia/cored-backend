<?php

namespace App\Domain\PaymentGateway\Support\Routing;

use App\Domain\PaymentGateway\Enums\ChannelRoutingType;

readonly class ChannelPolicyDefinition
{
    /**
     * @param  list<RoutePolicyDefinition>  $routes
     * @param  array<string, string>  $singleOverrides  bank slug => gateway name
     * @param  list<string>  $avoidTags
     */
    public function __construct(
        public string $key,
        public ChannelRoutingType $type,
        public array $routes,
        public ?string $cheapAltCode = null,
        public array $singleOverrides = [],
        public array $avoidTags = [],
        public ?string $qrisProxyPrimary = null,
        public ?FeePolicyDefinition $sharedFee = null,
    ) {}

    /**
     * @param  array<string, mixed>  $node
     */
    public static function fromPolicyNode(string $key, array $node): self
    {
        $typeRaw = $node['type'] ?? null;
        if (! is_string($typeRaw)) {
            throw new \InvalidArgumentException("Channel {$key} requires type.");
        }

        $type = match ($typeRaw) {
            'single' => ChannelRoutingType::Single,
            'dual' => ChannelRoutingType::Dual,
            default => throw new \InvalidArgumentException("Channel {$key} has invalid type: {$typeRaw}"),
        };

        $cheapAlt = isset($node['cheap_alt']) && is_string($node['cheap_alt']) ? $node['cheap_alt'] : null;
        $avoid = self::stringList($node['avoid'] ?? []);
        $overrides = self::stringMap($node['single_overrides'] ?? []);

        if ($key === 'linkaja') {
            return self::linkajaFromNode($key, $node, $avoid);
        }

        if ($type === ChannelRoutingType::Single) {
            return self::singleFromNode($key, $node, $cheapAlt);
        }

        return self::dualFromNode($key, $node, $cheapAlt, $overrides, $avoid);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function singleFromNode(string $key, array $node, ?string $cheapAlt): self
    {
        if (! isset($node['pg']) || ! is_string($node['pg'])) {
            throw new \InvalidArgumentException("Single channel {$key} requires pg.");
        }

        $vatInclusive = $key === 'qris' && strtolower($node['pg']) === 'midtrans';

        $route = new RoutePolicyDefinition(
            gateway: strtolower($node['pg']),
            settlementDays: RoutePolicyDefinition::parseSettlementDays((string) ($node['settlement'] ?? 'H+2')),
            fee: FeePolicyDefinition::fromMixed($node['fee'] ?? throw new \InvalidArgumentException("Channel {$key} requires fee."), $vatInclusive),
            role: \App\Domain\PaymentGateway\Enums\PaymentRouteRole::Only,
        );

        return new self(
            key: $key,
            type: ChannelRoutingType::Single,
            routes: [$route],
            cheapAltCode: $cheapAlt,
        );
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, string>  $overrides
     * @param  list<string>  $avoid
     */
    private static function dualFromNode(
        string $key,
        array $node,
        ?string $cheapAlt,
        array $overrides,
        array $avoid,
    ): self {
        $routes = [];

        if (isset($node['primary']) && is_array($node['primary'])) {
            $vatInclusive = $key === 'qris' && strtolower((string) ($node['primary']['pg'] ?? '')) === 'midtrans';
            $routes[] = RoutePolicyDefinition::fromPolicyNode(
                $node['primary'],
                \App\Domain\PaymentGateway\Enums\PaymentRouteRole::Primary,
                $vatInclusive,
            );
        }

        if (isset($node['fallback']) && is_array($node['fallback'])) {
            $policyTag = ($key === 'qris' && strtolower((string) ($node['fallback']['pg'] ?? '')) === 'ipaymu')
                ? 'ipaymu_h2'
                : null;

            $routes[] = RoutePolicyDefinition::fromPolicyNode(
                $node['fallback'],
                \App\Domain\PaymentGateway\Enums\PaymentRouteRole::Fallback,
                false,
                $policyTag,
            );
        }

        if ($routes === []) {
            throw new \InvalidArgumentException("Dual channel {$key} requires primary and/or fallback routes.");
        }

        return new self(
            key: $key,
            type: ChannelRoutingType::Dual,
            routes: $routes,
            cheapAltCode: $cheapAlt,
            singleOverrides: $overrides,
            avoidTags: $avoid,
        );
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $avoid
     */
    private static function linkajaFromNode(string $key, array $node, array $avoid): self
    {
        $sharedFee = isset($node['fee'])
            ? FeePolicyDefinition::fromMixed($node['fee'])
            : null;

        return new self(
            key: $key,
            type: ChannelRoutingType::QrisProxy,
            routes: [],
            avoidTags: $avoid,
            qrisProxyPrimary: is_string($node['primary'] ?? null) ? $node['primary'] : 'qris',
            sharedFee: $sharedFee,
        );
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    /**
     * @return array<string, string>
     */
    private static function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $bank => $gateway) {
            if (is_string($bank) && is_string($gateway)) {
                $map[$bank] = strtolower($gateway);
            }
        }

        return $map;
    }

    public function overrideGatewayForBank(?string $bankSlug): ?string
    {
        if ($bankSlug === null || $bankSlug === '') {
            return null;
        }

        return $this->singleOverrides[$bankSlug] ?? null;
    }

    public function isAvoidedTag(?string $tag): bool
    {
        if ($tag === null || $tag === '') {
            return false;
        }

        if (in_array($tag, $this->avoidTags, true)) {
            return true;
        }

        // Hard block iPaymu H+0 QRIS paths even if only tagged implicitly.
        return $tag === 'ipaymu_h0';
    }
}
