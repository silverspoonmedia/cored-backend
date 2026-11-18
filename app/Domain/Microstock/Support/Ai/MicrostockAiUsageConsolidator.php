<?php

namespace App\Domain\Microstock\Support\Ai;

use App\Domain\Microstock\Services\BillingLedgerService;
use App\Domain\Microstock\Services\UnknownModelBillingConsolidationService;
use App\Support\Ai\Contracts\AiUsageConsolidator;

/**
 * Microstock's consolidation hook, published to the kernel.
 *
 * Curating a model rate in `silverspoonmedia/ai` must recalculate the prepaid usage
 * Microstock billed provisionally. The arithmetic, the credit balance and the
 * ledger adjustment rows stay in Microstock; the catalog only asks for them.
 */
final class MicrostockAiUsageConsolidator implements AiUsageConsolidator
{
    public function __construct(
        private readonly UnknownModelBillingConsolidationService $consolidation,
        private readonly BillingLedgerService $ledger,
    ) {}

    public function consolidate(string $providerId, string $modelCardId): array
    {
        return $this->consolidation->consolidate($providerId, $modelCardId);
    }

    public function formatUsd(int $microUsd, bool $signed = false): string
    {
        return $this->ledger->formatUsd($microUsd, $signed);
    }
}
