<?php

namespace App\Domain\Microstock\Support\Ai;

use App\Domain\Microstock\Models\AiUsageEvent;
use App\Domain\Microstock\Services\BillingLedgerService;
use App\Support\Ai\Contracts\AiUsageLedger;

/**
 * Microstock's AI usage ledger, published to the kernel.
 *
 * `silverspoonmedia/ai` renders the admin usage dashboard from this seam instead of
 * importing `microstock_ai_usage_events`; the table, its relations and the money
 * formatting stay owned here.
 */
final class MicrostockAiUsageLedger implements AiUsageLedger
{
    public function __construct(
        private readonly BillingLedgerService $ledger,
    ) {}

    public function usageEventModel(): string
    {
        return AiUsageEvent::class;
    }

    public function usageEventRelations(): array
    {
        return [
            'file:id,name',
            'folder:id,name',
            'user:id,name,email',
            'inferenceProvider:id,slug,name',
            'aiModelRateSnapshot:id,model_slug,inference_provider_id,input_per_million_usd,output_per_million_usd,captured_at',
        ];
    }

    public function formatUsd(int $microUsd, bool $signed = false): string
    {
        return $this->ledger->formatUsd($microUsd, $signed);
    }
}
