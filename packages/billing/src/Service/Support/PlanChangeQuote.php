<?php

namespace App\Domain\Service\Support;

use App\Domain\Service\Enums\PurchaseChangeType;
use App\Domain\Service\Models\ServiceEntitlement;

/**
 * Immutable checkout quote for main/addon plan changes.
 */
final class PlanChangeQuote
{
    public function __construct(
        public readonly PurchaseChangeType $changeType,
        public readonly float $listAmount,
        public readonly float $creditAmount,
        public readonly float $chargeAmount,
        public readonly int $surplusDays,
        public readonly ?string $previousEntitlementId,
        public readonly int $periodDays = 30,
    ) {}

    public function isZeroCharge(): bool
    {
        return $this->chargeAmount <= 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'change_type' => $this->changeType->value,
            'list_amount' => round($this->listAmount, 2),
            'credit_amount' => round($this->creditAmount, 2),
            'charge_amount' => round($this->chargeAmount, 2),
            'surplus_days' => $this->surplusDays,
            'previous_entitlement_id' => $this->previousEntitlementId,
            'period_days' => $this->periodDays,
        ];
    }

    public static function fullPrice(PurchaseChangeType $type, float $listAmount, ?ServiceEntitlement $previous = null): self
    {
        return new self(
            changeType: $type,
            listAmount: $listAmount,
            creditAmount: 0.0,
            chargeAmount: $listAmount,
            surplusDays: 0,
            previousEntitlementId: $previous?->id,
        );
    }
}
