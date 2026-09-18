<?php

namespace App\Console\Commands\Vtual;

use App\Domain\Vtual\Enums\ContentAiAuditStatus;
use App\Domain\Vtual\Enums\ContentKind;
use App\Domain\Vtual\Integrations\Ai\ContentAiClassifier;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Models\ContentAiAuditItem;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

/**
 * Drains the `vtual_content_ai_audit` pool.
 *
 * This is the other half of the async AI pass: ContentAiAuditService writes
 * pending rows on hydrate, and this command turns them into verdicts. Without
 * it the pool is write-only and the AI never runs.
 *
 * The AI may correct BOTH layers — `content_type` (music / non_music) and the
 * sub-category — because the rows it receives are exactly the ones the program
 * classifier could not decide. A row it cannot decide stays queued for a later
 * run until `--max-attempts` is exhausted.
 *
 * Each call takes ~15-25 s, so runs are small and batched by `--limit`.
 */
#[AsCommand(name: 'vtual:audit-content-ai')]
class AuditContentAiCommand extends Command
{
    protected $signature = 'vtual:audit-content-ai
                            {--limit=25 : Audit pool rows per run}
                            {--max-attempts=3 : Stop retrying a row after this many attempts}
                            {--stale-minutes=15 : Return rows stuck in processing for longer than this to pending}';

    protected $description = 'Drain the vTual AI classification audit pool (music/non-music + sub-category)';

    public function handle(ContentAiClassifier $ai): int
    {
        if (! $ai->enabled()) {
            $this->error('AI audit is not enabled/configured (vtual.content_classification.ai.* + a bound AI connection).');

            return self::FAILURE;
        }

        $maxAttempts = max(1, (int) $this->option('max-attempts'));
        $requeued = $this->requeueStale(max(1, (int) $this->option('stale-minutes')));
        if ($requeued > 0) {
            $this->line("Requeued {$requeued} stale processing row(s).");
        }

        $items = $this->claimPending(max(1, (int) $this->option('limit')), $maxAttempts);

        if ($items->isEmpty()) {
            $this->info('Nothing to audit.');

            return self::SUCCESS;
        }

        $counts = ['music' => 0, 'non_music' => 0, 'unresolved' => 0, 'failed' => 0];

        foreach ($items as $item) {
            $this->drainOne($item, $ai, $counts);
        }

        $this->table(
            ['music', 'non_music', 'unresolved', 'failed', 'processed'],
            [[$counts['music'], $counts['non_music'], $counts['unresolved'], $counts['failed'], $items->count()]],
        );

        return self::SUCCESS;
    }

    /**
     * A run killed mid-flight leaves rows in `processing`; nothing else would
     * ever pick them up. `attempts` was already charged at claim time, so this
     * cannot loop forever.
     */
    private function requeueStale(int $staleMinutes): int
    {
        return ContentAiAuditItem::query()
            ->where('status', ContentAiAuditStatus::Processing)
            ->where('updated_at', '<', now()->subMinutes($staleMinutes))
            ->update([
                'status' => ContentAiAuditStatus::Pending->value,
                'updated_at' => now(),
            ]);
    }

    /**
     * @return Collection<int, ContentAiAuditItem>
     */
    private function claimPending(int $limit, int $maxAttempts): Collection
    {
        return DB::transaction(function () use ($limit, $maxAttempts): Collection {
            /** @var Collection<int, ContentAiAuditItem> $pending */
            $pending = ContentAiAuditItem::query()
                ->where('status', ContentAiAuditStatus::Pending)
                ->where('attempts', '<', $maxAttempts)
                ->orderBy('requested_at')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            if ($pending->isEmpty()) {
                return $pending;
            }

            $ids = $pending->pluck('id')->all();

            ContentAiAuditItem::query()
                ->whereIn('id', $ids)
                ->update([
                    'status' => ContentAiAuditStatus::Processing->value,
                    // Charged before the call so a crash still counts the attempt.
                    'attempts' => DB::raw('attempts + 1'),
                    'updated_at' => now(),
                ]);

            return ContentAiAuditItem::query()->whereIn('id', $ids)->get();
        }, 3);
    }

    /**
     * @param  array{music: int, non_music: int, unresolved: int, failed: int}  $counts
     */
    private function drainOne(ContentAiAuditItem $item, ContentAiClassifier $ai, array &$counts): void
    {
        $content = Content::query()->find($item->content_id);

        // Content deleted between enqueue and drain — nothing left to classify.
        if ($content === null) {
            $this->mark($item, ContentAiAuditStatus::Done, null);
            $counts['unresolved']++;

            return;
        }

        try {
            $result = $ai->classifyContent([
                'external_id' => $content->external_id,
                'title' => $content->title,
                'description' => $content->description,
                'duration_seconds' => $content->durationSeconds(),
            ]);
        } catch (Throwable $e) {
            $this->mark($item, ContentAiAuditStatus::Failed, $e->getMessage());
            $counts['failed']++;

            return;
        }

        // No verdict (unreadable answer, low confidence, HTTP failure). Leave the
        // row's classification untouched and let a later run retry it.
        if ($result === null) {
            $this->mark($item, ContentAiAuditStatus::Failed, 'no_verdict');
            $counts['unresolved']++;

            return;
        }

        if ($result['is_music']) {
            $content->content_type = ContentKind::Music;
            $content->content_category = $result['category'];
            $content->content_category_detail = $result['detail'];
            $counts['music']++;
        } else {
            $content->content_type = ContentKind::NonMusic;
            // The AI corrected the whole row: a stale sub-category must not
            // survive a non-music verdict.
            $content->content_category = null;
            $content->content_category_detail = null;
            $counts['non_music']++;
        }

        $content->save();

        $this->mark($item, ContentAiAuditStatus::Done, null);
    }

    private function mark(ContentAiAuditItem $item, ContentAiAuditStatus $status, ?string $error): void
    {
        ContentAiAuditItem::query()->whereKey($item->id)->update([
            'status' => $status->value,
            'processed_at' => now(),
            'error' => $error !== null ? mb_substr($error, 0, 1000) : null,
            'updated_at' => now(),
        ]);
    }
}
