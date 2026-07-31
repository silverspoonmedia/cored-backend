<?php

namespace App\Domain\PaymentGateway\Support\Routing;

use App\Domain\PaymentGateway\Enums\PaymentRouteRole;

readonly class RoutePolicyDefinition
{
    public function __construct(
        public string $gateway,
        public int $settlementDays,
        public FeePolicyDefinition $fee,
        public PaymentRouteRole $role,
        public ?string $providerPaymentType = null,
        public ?string $providerPaymentChannel = null,
        public ?string $policyTag = null,
    ) {}

    /**
     * @param  array<string, mixed>  $node
     */
    public static function fromPolicyNode(
        array $node,
        PaymentRouteRole $role,
        bool $vatInclusive = false,
        ?string $policyTag = null,
    ): self {
        if (! isset($node['pg']) || ! is_string($node['pg'])) {
            throw new \InvalidArgumentException('Route policy node requires string pg.');
        }

        $settlement = $node['settlement'] ?? null;
        if (! is_string($settlement)) {
            throw new \InvalidArgumentException('Route policy node requires settlement like H+2.');
        }

        if (! isset($node['fee'])) {
            throw new \InvalidArgumentException('Route policy node requires fee.');
        }

        return new self(
            gateway: strtolower($node['pg']),
            settlementDays: self::parseSettlementDays($settlement),
            fee: FeePolicyDefinition::fromMixed($node['fee'], $vatInclusive),
            role: $role,
            policyTag: $policyTag,
        );
    }

    public static function parseSettlementDays(string $settlement): int
    {
        if (preg_match('/^H\+(\d+)$/i', trim($settlement), $matches)) {
            return (int) $matches[1];
        }

        throw new \InvalidArgumentException("Invalid settlement tier: {$settlement}");
    }
}
