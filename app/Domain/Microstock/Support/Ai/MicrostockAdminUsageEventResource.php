<?php

namespace App\Domain\Microstock\Support\Ai;

use App\Domain\Microstock\Http\Resources\AdminBillingUsageEventResource;
use App\Domain\Microstock\Models\AiUsageEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The admin usage-event row `silverspoon/ai` renders.
 *
 * The dashboard ships in the AI package but the ledger rows it lists belong to
 * Microstock, so the package resolves this class through
 * `config('ai.usage_event_resource')` and never imports a Microstock model. The
 * base row is Microstock's own `AdminBillingUsageEventResource`; the seam adds
 * nothing and exists so the package knows which class to instantiate.
 *
 * @mixin AiUsageEvent
 */
final class MicrostockAdminUsageEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (new AdminBillingUsageEventResource($this->resource))->toArray($request);
    }
}
