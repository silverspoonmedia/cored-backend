<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ContentAiAuditStatus;
use App\Domain\Vtual\Enums\ContentKind;
use App\Domain\Vtual\Integrations\Ai\ContentAiClassifier;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Models\ContentAiAuditItem;
use Illuminate\Support\Facades\Log;

/**
 * Enqueue contents for the asynchronous AI audit pass.
 *
 * Two reasons a row needs the AI: the program classifier could not decide the
 * music/non-music layer (content_type null — ambiguous short-form video), or it
 * decided music but the sub-category heuristics came up empty.
 *
 * Only fires when the AI audit is enabled AND configured (bound AI connection +
 * model); otherwise this is a no-op and the program pass stays the sole source.
 * Never throws — audit failures must never break the hydrate path.
 *
 * This service only writes the pool. The drainer is `vtual:audit-content-ai`.
 */
class ContentAiAuditService
{
    public function __construct(
        private readonly ContentAiClassifier $ai,
    ) {}

    /**
     * Record a pending audit for a row the program classifier left incomplete.
     * Idempotent per content: a pending row is not duplicated.
     */
    public function enqueueIfEligible(Content $content): void
    {
        try {
            if (! $this->ai->enabled()) {
                return;
            }

            if (! $this->needsAudit($content)) {
                return;
            }

            $pending = ContentAiAuditItem::query()
                ->where('content_id', $content->id)
                ->where('status', ContentAiAuditStatus::Pending)
                ->exists();

            if ($pending) {
                return;
            }

            ContentAiAuditItem::query()->create([
                'content_id' => $content->id,
                'status' => ContentAiAuditStatus::Pending,
                'attempts' => 0,
                'requested_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('vtual.ai_audit_enqueue_failed', [
                'content_id' => (string) $content->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * A row needs the AI when the program layer produced no verdict at all, or
     * produced "music" without a sub-category.
     */
    private function needsAudit(Content $content): bool
    {
        if ($content->content_type === null) {
            return true;
        }

        return $content->content_type === ContentKind::Music && $content->content_category === null;
    }
}
