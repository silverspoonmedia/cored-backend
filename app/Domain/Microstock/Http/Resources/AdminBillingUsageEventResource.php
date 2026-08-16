<?php

namespace App\Domain\Microstock\Http\Resources;

use App\Domain\Microstock\Models\AiUsageEvent;
use App\Domain\Microstock\Services\BillingLedgerService;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiUsageEvent */
class AdminBillingUsageEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $ledger = app(BillingLedgerService::class);
        $this->loadMissing('aiModelRateSnapshot');
        $base = (new BillingUsageEventResource($this->resource))->toArray($request);

        return array_merge($base, [
            'rate_snapshot' => $this->when(
                $this->relationLoaded('aiModelRateSnapshot') && $this->aiModelRateSnapshot !== null,
                fn () => [
                    'input_per_million_usd' => (float) $this->aiModelRateSnapshot->input_per_million_usd,
                    'output_per_million_usd' => (float) $this->aiModelRateSnapshot->output_per_million_usd,
                    'captured_at' => $this->aiModelRateSnapshot->captured_at?->toIso8601String(),
                ],
            ),
            'provider_cost_usd' => $ledger->formatUsd((int) $this->provider_cost_micro_usd),
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'inference_provider' => $this->whenLoaded('inferenceProvider', fn (): ?array => $this->inferenceProvider === null ? null : [
                'id' => $this->inferenceProvider->id,
                'slug' => $this->inferenceProvider->slug,
                'name' => $this->inferenceProvider->name,
            ]),
        ]);
    }
}
