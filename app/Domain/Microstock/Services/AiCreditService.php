<?php

namespace App\Domain\Microstock\Services;

use App\Domain\Microstock\Enums\AiCreditTransactionType;
use App\Domain\Microstock\Models\AiBalance;
use App\Domain\Microstock\Models\AiCreditTransaction;
use App\Domain\Microstock\Support\MicrostockCatalog;
use App\Domain\Microstock\Support\MicrostockRealtime;
use App\Domain\Service\Models\ServicePurchase;
use Illuminate\Support\Facades\DB;

class AiCreditService
{
    /**
     * MicrostockRealtime is resolved lazily (app()) to break the circular
     * dependency: CollectionService → AiBillingService → AiCreditService →
     * MicrostockRealtime → CollectionService.
     */
    public function __construct() {}

    private function realtime(): MicrostockRealtime
    {
        return app(MicrostockRealtime::class);
    }

    public function balance(string $userId): int
    {
        return (int) (AiBalance::query()->find($userId)?->balance_micro_usd ?? 0);
    }

    public function creditFromPurchase(ServicePurchase $purchase): void
    {
        $purchase->loadMissing('service');
        $service = $purchase->service;

        if ($service === null || $service->entitlement !== MicrostockCatalog::ENTITLEMENT_AI_CREDITS) {
            return;
        }

        $this->creditPurchaseRow(
            (string) $purchase->id,
            (string) $purchase->user_id,
            (float) ($purchase->charge_amount ?? $purchase->list_amount ?? $purchase->display_amount ?? 0),
        );
    }

    /**
     * Same credit, addressed by id.
     *
     * The billing package hands over an id (kernel `FulfillmentContext`) instead
     * of a model, so the package never imports one.
     */
    public function creditFromPurchaseId(string $purchaseId): void
    {
        $purchase = ServicePurchase::query()->with('service')->find($purchaseId);

        if ($purchase === null) {
            return;
        }

        $this->creditFromPurchase($purchase);
    }

    private function creditPurchaseRow(string $purchaseId, string $userId, float $usd): void
    {
        if (AiCreditTransaction::query()
            ->where('reference_type', 'service_purchase')
            ->where('reference_id', $purchaseId)
            ->where('type', AiCreditTransactionType::Purchase)
            ->exists()
        ) {
            return;
        }

        $microUsd = (int) round($usd * 1_000_000);

        if ($microUsd <= 0) {
            return;
        }

        $this->addCredit(
            $userId,
            $microUsd,
            AiCreditTransactionType::Purchase,
            'service_purchase',
            $purchaseId,
        );
    }

    public function addCredit(
        string $userId,
        int $amountMicroUsd,
        AiCreditTransactionType $type,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): void {
        if ($amountMicroUsd <= 0) {
            return;
        }

        DB::transaction(function () use ($userId, $amountMicroUsd, $type, $referenceType, $referenceId): void {
            $balance = AiBalance::query()->lockForUpdate()->firstOrCreate(
                ['user_id' => $userId],
                ['balance_micro_usd' => 0, 'updated_at' => now()],
            );

            $newBalance = (int) $balance->balance_micro_usd + $amountMicroUsd;
            $balance->forceFill([
                'balance_micro_usd' => $newBalance,
                'updated_at' => now(),
            ])->save();

            AiCreditTransaction::query()->create([
                'user_id' => $userId,
                'amount_micro_usd' => $amountMicroUsd,
                'type' => $type,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'balance_after' => $newBalance,
                'created_at' => now(),
            ]);
        });

        $this->realtime()->billingChanged($userId, $type->value);
    }

    public function debit(
        string $userId,
        int $amountMicroUsd,
        string $referenceType,
        string $referenceId,
        AiCreditTransactionType $type = AiCreditTransactionType::AnalysisDebit,
    ): void {
        if ($amountMicroUsd <= 0) {
            return;
        }

        DB::transaction(function () use ($userId, $amountMicroUsd, $referenceType, $referenceId, $type): void {
            $balance = AiBalance::query()->lockForUpdate()->firstOrCreate(
                ['user_id' => $userId],
                ['balance_micro_usd' => 0, 'updated_at' => now()],
            );

            $newBalance = (int) $balance->balance_micro_usd - $amountMicroUsd;
            $balance->forceFill([
                'balance_micro_usd' => $newBalance,
                'updated_at' => now(),
            ])->save();

            AiCreditTransaction::query()->create([
                'user_id' => $userId,
                'amount_micro_usd' => -$amountMicroUsd,
                'type' => $type,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'balance_after' => $newBalance,
                'created_at' => now(),
            ]);
        });

        $this->realtime()->billingChanged($userId, $type->value);
    }
}
