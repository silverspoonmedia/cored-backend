<?php

namespace App\Domain\Microstock\Services;

use App\Domain\Microstock\Enums\AiCreditTransactionType;
use App\Domain\Microstock\Models\AiCreditTransaction;
use App\Domain\Microstock\Models\AiUsageEvent;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServicePurchase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BillingLedgerService
{
    public function __construct(
        private readonly AiBillingService $billing,
    ) {}

    /**
     * @return LengthAwarePaginator<int, AiCreditTransaction>
     */
    public function ledgerPaginated(
        string $userId,
        int $perPage = 20,
        ?string $from = null,
        ?string $to = null,
    ): LengthAwarePaginator {
        $query = AiCreditTransaction::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $this->applyDateFilters($query, $from, $to);

        $paginator = $query->paginate($perPage);

        $this->attachLedgerDescriptions($paginator->getCollection());

        return $paginator;
    }

    /**
     * @return LengthAwarePaginator<int, AiUsageEvent>
     */
    public function usageEventsPaginated(
        string $userId,
        int $perPage = 20,
        ?string $from = null,
        ?string $to = null,
    ): LengthAwarePaginator {
        $query = AiUsageEvent::query()
            ->where('user_id', $userId)
            ->whereNull('voided_at')
            ->with([
                'file:id,name',
                'folder:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $this->applyDateFilters($query, $from, $to);

        return $query->paginate($perPage);
    }

    public function microUsdToUsd(int $microUsd): float
    {
        return $this->billing->microUsdToUsd($microUsd);
    }

    public function formatUsd(int $microUsd, bool $signed = false): string
    {
        $usd = $this->microUsdToUsd(abs($microUsd));
        $formatted = number_format($usd, 2, '.', '');

        if ($signed && $microUsd !== 0) {
            return ($microUsd > 0 ? '+' : '-').'$'.$formatted;
        }

        return '$'.$formatted;
    }

    /**
     * @param  Collection<int, AiCreditTransaction>  $transactions
     */
    private function attachLedgerDescriptions(Collection $transactions): void
    {
        if ($transactions->isEmpty()) {
            return;
        }

        $metadataIds = $transactions
            ->filter(fn (AiCreditTransaction $tx): bool => $tx->type === AiCreditTransactionType::AnalysisDebit
                && $tx->reference_type === 'asset_metadata'
                && $tx->reference_id !== null)
            ->pluck('reference_id')
            ->unique()
            ->values();

        $purchaseIds = $transactions
            ->filter(fn (AiCreditTransaction $tx): bool => $tx->type === AiCreditTransactionType::Purchase
                && $tx->reference_type === 'service_purchase'
                && $tx->reference_id !== null)
            ->pluck('reference_id')
            ->unique()
            ->values();

        $metadataById = $metadataIds->isEmpty()
            ? collect()
            : AssetMetadata::query()
                ->whereIn('id', $metadataIds)
                ->with(['file:id,name,folder_id', 'file.folder:id,name'])
                ->get()
                ->keyBy('id');

        $purchasesById = $purchaseIds->isEmpty()
            ? collect()
            : ServicePurchase::query()
                ->whereIn('id', $purchaseIds)
                ->with('service:id,code,name')
                ->get()
                ->keyBy('id');

        foreach ($transactions as $transaction) {
            $transaction->setAttribute('ledger_description', $this->describeTransaction(
                $transaction,
                $metadataById,
                $purchasesById,
            ));
            $transaction->setAttribute('ledger_reference', $this->referencePayload(
                $transaction,
                $metadataById,
                $purchasesById,
            ));
        }
    }

    /**
     * @param  Collection<string, AssetMetadata>  $metadataById
     * @param  Collection<string, ServicePurchase>  $purchasesById
     */
    private function describeTransaction(
        AiCreditTransaction $transaction,
        Collection $metadataById,
        Collection $purchasesById,
    ): string {
        return match ($transaction->type) {
            AiCreditTransactionType::Purchase => $this->describePurchase($transaction, $purchasesById),
            AiCreditTransactionType::AnalysisDebit => $this->describeAnalysisDebit($transaction, $metadataById),
            AiCreditTransactionType::Adjustment => __('api/microstock.ledger.adjustment'),
        };
    }

    /**
     * @param  Collection<string, ServicePurchase>  $purchasesById
     */
    private function describePurchase(AiCreditTransaction $transaction, Collection $purchasesById): string
    {
        /** @var ServicePurchase|null $purchase */
        $purchase = $transaction->reference_id !== null
            ? $purchasesById->get($transaction->reference_id)
            : null;
        /** @var Service|null $service */
        $service = $purchase?->service;
        $serviceName = $service->name ?? $service->code;

        return __('api/microstock.ledger.purchase', [
            'service' => $serviceName ?: __('api/microstock.ledger.purchase_fallback'),
        ]);
    }

    /**
     * @param  Collection<string, AssetMetadata>  $metadataById
     */
    private function describeAnalysisDebit(AiCreditTransaction $transaction, Collection $metadataById): string
    {
        $metadata = $transaction->reference_id !== null
            ? $metadataById->get($transaction->reference_id)
            : null;
        $fileName = $metadata?->file?->name;
        $folderName = $metadata?->file?->folder?->name;

        if ($fileName !== null && $folderName !== null) {
            return __('api/microstock.ledger.analysis_debit_with_folder', [
                'file' => $fileName,
                'folder' => $folderName,
            ]);
        }

        if ($fileName !== null) {
            return __('api/microstock.ledger.analysis_debit', ['file' => $fileName]);
        }

        return __('api/microstock.ledger.analysis_debit_fallback');
    }

    /**
     * @param  Collection<string, AssetMetadata>  $metadataById
     * @param  Collection<string, ServicePurchase>  $purchasesById
     * @return array<string, mixed>
     */
    private function referencePayload(
        AiCreditTransaction $transaction,
        Collection $metadataById,
        Collection $purchasesById,
    ): array {
        if ($transaction->type === AiCreditTransactionType::AnalysisDebit
            && $transaction->reference_id !== null
        ) {
            $metadata = $metadataById->get($transaction->reference_id);

            return [
                'file_id' => $metadata?->file_id,
                'file_name' => $metadata?->file?->name,
                'folder_id' => $metadata?->file?->folder_id,
                'folder_name' => $metadata?->file?->folder?->name,
                'metadata_id' => $transaction->reference_id,
            ];
        }

        if ($transaction->type === AiCreditTransactionType::Purchase
            && $transaction->reference_id !== null
        ) {
            $purchase = $purchasesById->get($transaction->reference_id);

            return [
                'purchase_id' => $transaction->reference_id,
                'purchase_code' => $purchase?->service?->code,
                'purchase_name' => $purchase?->service?->name,
            ];
        }

        return [];
    }

    /**
     * @param  Builder<AiCreditTransaction>| Builder<AiUsageEvent>  $query
     */
    private function applyDateFilters(mixed $query, ?string $from, ?string $to): void
    {
        if ($from !== null && $from !== '') {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to !== null && $to !== '') {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }
    }
}
