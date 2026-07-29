<?php

namespace App\Domain\Microstock\Http\Resources;

use App\Domain\Microstock\Models\AiCreditTransaction;
use App\Domain\Microstock\Services\BillingLedgerService;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiCreditTransaction */
class BillingLedgerEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $ledger = app(BillingLedgerService::class);
        $amountMicro = (int) $this->amount_micro_usd;
        $balanceAfterMicro = (int) $this->balance_after;

        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->toIso8601String(),
            'type' => $this->type?->value,
            'description' => (string) ($this->ledger_description ?? ''),
            'amount_micro_usd' => $amountMicro,
            'amount_usd' => $ledger->formatUsd($amountMicro, signed: true),
            'balance_after_micro_usd' => $balanceAfterMicro,
            'balance_after_usd' => $ledger->formatUsd($balanceAfterMicro),
            'reference' => $this->ledger_reference ?? [],
        ];
    }
}
