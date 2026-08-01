<?php

namespace App\Domain\Microstock\Services;

use App\Domain\Microstock\Enums\AiCreditTransactionType;
use App\Domain\Microstock\Models\AiBalance;
use App\Domain\Microstock\Models\AiCreditTransaction;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Support\MicrostockCatalog;
use Illuminate\Support\Facades\DB;

class AiCreditService
{
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

        if (AiCreditTransaction::query()
            ->where('reference_type', 'service_purchase')
            ->where('reference_id', $purchase->id)
            ->where('type', AiCreditTransactionType::Purchase)
            ->exists()
        ) {
            return;
        }

        $usd = (float) ($purchase->charge_amount ?? $purchase->list_amount ?? $purchase->display_amount ?? 0);
        $microUsd = (int) round($usd * 1_000_000);

        if ($microUsd <= 0) {
            return;
        }

        $this->addCredit(
            (string) $purchase->user_id,
            $microUsd,
            AiCreditTransactionType::Purchase,
            'service_purchase',
            (string) $purchase->id,
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
    }
}
