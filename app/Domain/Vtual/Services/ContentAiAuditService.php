<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ContentKind;
use App\Domain\Vtual\Integrations\Ai\ContentAiClassifier;
use App\Domain\Vtual\Models\Content;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Enqueue music contents for the asynchronous AI sub-category audit pass.
 *
 * Only fires when the AI audit is enabled AND configured (bound AI connection +
 * model); otherwise this is a no-op and the heuristic pass stays the sole
 * sub-category source. Never throws — audit failures must never break the
 * hydrate path.
 */
class ContentAiAuditService
{
    public function __construct(
        private readonly ContentAiClassifier $ai,
    ) {}

    /**
     * Record a pending audit for a music row the heuristics left unclassified.
     * Idempotent per content: a pending row is not duplicated.
     */
    public function enqueueIfEligible(Content $content): void
    {
        try {
            if (! $this->ai->enabled()) {
                return;
            }

            if ($content->content_type !== ContentKind::Music || $content->content_category !== null) {
                return;
            }

            $pending = DB::table('vtual_content_ai_audit')
                ->where('content_id', $content->id)
                ->where('status', 'pending')
                ->exists();

            if ($pending) {
                return;
            }

            DB::table('vtual_content_ai_audit')->insert([
                'id' => (string) Str::uuid7(),
                'content_id' => $content->id,
                'status' => 'pending',
                'attempts' => 0,
                'requested_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('vtual.ai_audit_enqueue_failed', [
                'content_id' => (string) $content->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
