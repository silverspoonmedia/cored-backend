<?php

namespace App\Domain\Microstock\Http\Resources;

use App\Domain\Microstock\Models\AiUsageEvent;
use App\Domain\Microstock\Services\BillingLedgerService;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiUsageEvent */
class BillingUsageEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $ledger = app(BillingLedgerService::class);

        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->toIso8601String(),
            'stage' => $this->stage,
            'alias_model' => $this->alias_model,
            'resolved_model' => $this->resolved_model,
            'prompt_tokens' => (int) $this->prompt_tokens,
            'completion_tokens' => (int) $this->completion_tokens,
            'total_tokens' => (int) $this->total_tokens,
            'provider_cost_usd' => $ledger->formatUsd((int) $this->provider_cost_micro_usd),
            'billable_usd' => $ledger->formatUsd((int) $this->billable_micro_usd),
            'billing_source' => $this->billing_source,
            'file_id' => $this->file_id,
            'file_name' => $this->file?->name,
            'folder_id' => $this->folder_id,
            'folder_name' => $this->folder?->name,
            'analysis_run_id' => $this->analysis_run_id,
            'billed_at' => $this->billed_at?->toIso8601String(),
        ];
    }
}
