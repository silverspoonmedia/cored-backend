<?php

namespace App\Console\Commands\Vtual;

use App\Domain\Vtual\Enums\ContentKind;
use App\Domain\Vtual\Integrations\Ai\ContentAiClassifier;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Services\ContentCategoryClassifier;
use App\Domain\Vtual\Services\ContentKindClassifier;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Backfill both classification layers on already-hydrated YouTube rows.
 *
 * This is the manual counterpart to the hydrate path (PlatformVideoDtoMapper →
 * ContentService). It is weaker than hydrate by design: the stored row has no
 * categoryId, topicCategories or tags (those are transient mapper inputs), so
 * the program pass here can only use title / description / duration / channel
 * name. The catalog artist fallback still runs, because the channel name is
 * enough to tell an uploader's own song from someone else's.
 *
 * `--ai` is the escape hatch for rows the program pass still cannot decide —
 * ambiguous short-form videos, and music rows without a sub-category. It writes
 * both layers, exactly like the `vtual:audit-content-ai` drainer.
 *
 * `--refresh` recomputes rows that already carry a verdict. Use it after a
 * classifier change: the default run never overwrites an existing label, so a
 * wrong verdict would otherwise survive every backfill.
 */
#[AsCommand(name: 'vtual:classify-contents')]
class ClassifyContentsCommand extends Command
{
    protected $signature = 'vtual:classify-contents
                            {--chunk=500 : Rows per chunk}
                            {--include-classified : Include already-classified rows in the work set}
                            {--refresh : Recompute and overwrite existing verdicts (use after a classifier change; pair with --ai to refill what the program pass cannot re-resolve)}
                            {--ai : Resolve rows the program pass cannot decide via the AI audit pass (requires configured endpoint/key)}';

    protected $description = 'Backfill content classification on hydrated YouTube contents (program pass, optional AI pass with --ai)';

    public function handle(
        ContentCategoryClassifier $classifier,
        ContentKindClassifier $kinds,
        ContentAiClassifier $ai,
    ): int {
        $chunk = max(1, (int) $this->option('chunk'));
        $includeClassified = (bool) $this->option('include-classified');
        $refresh = (bool) $this->option('refresh');
        $withAi = (bool) $this->option('ai');

        if ($withAi && ! $ai->enabled()) {
            $this->error('AI audit is not enabled/configured (vtual.content_classification.ai.* + a bound AI connection).');

            return self::FAILURE;
        }

        $query = Content::query()
            ->with('channel')
            ->whereHas('platform', fn ($q) => $q->where('key', 'youtube'))
            ->whereNotNull('hydrated_at')
            ->orderBy('id');

        // --refresh works the whole set; --include-classified widens an ordinary
        // run to rows that already have both layers (still never overwritten).
        if (! $refresh && ! $includeClassified) {
            $query->where(fn ($q) => $q->whereNull('content_category')->orWhereNull('content_type'));
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Nothing to classify.');

            return self::SUCCESS;
        }

        $this->info("Classifying {$total} row(s)…");

        $counts = array_fill_keys([
            'kind_music', 'kind_non_music',
            'cover', 'original', 'singing_stream',
            'ai_music', 'ai_non_music', 'ai_category',
            'cleared', 'unresolved',
        ], 0);
        $processed = 0;

        $query->chunkById($chunk, function ($rows) use ($classifier, $kinds, $ai, $withAi, $refresh, &$counts, &$processed): void {
            foreach ($rows as $content) {
                $video = [
                    'external_id' => $content->external_id,
                    'title' => $content->title,
                    'description' => $content->description,
                    'duration_seconds' => $content->durationSeconds(),
                    'type' => $content->type?->value ?? (string) $content->type,
                    'channel_title' => $content->channel?->display_name,
                ];

                $dirty = false;

                // Layer 1 — music / non-music (program signals only).
                if ($content->content_type === null || $refresh) {
                    $kind = $kinds->kind($video);
                    if ($kind !== null) {
                        if ($content->content_type !== $kind) {
                            $counts['kind_'.$kind->value]++;
                        }
                        $content->content_type = $kind;
                        $dirty = true;
                    }
                }

                // Layer 2 — sub-category heuristics.
                if ($content->content_category === null || $refresh) {
                    $classified = $classifier->evaluate($video);

                    if ($classified['category'] !== null) {
                        if ($content->content_category !== $classified['category']) {
                            $counts[$classified['category']->value] = ($counts[$classified['category']->value] ?? 0) + 1;
                        }
                        $content->content_category = $classified['category'];
                        $content->content_category_detail = $classified['detail'];
                        $dirty = true;
                    } elseif ($refresh) {
                        // The old verdict came from a rule that no longer holds —
                        // clearing it sends the row back to the AI audit instead
                        // of leaving a label nothing can justify.
                        if ($content->content_category !== null || $content->content_category_detail !== null) {
                            $counts['cleared']++;
                        }
                        $content->content_category = null;
                        $content->content_category_detail = null;
                        $dirty = true;
                    }
                }

                if ($dirty) {
                    $content->save();
                }

                // Layer 3 — AI, only for rows still incomplete.
                if ($withAi && $this->needsAi($content)) {
                    $audited = $ai->classifyContent($video);

                    if ($audited !== null) {
                        if ($audited['is_music']) {
                            $content->content_type = ContentKind::Music;
                            $content->content_category = $audited['category'];
                            $content->content_category_detail = $audited['detail'];
                            $counts['ai_music']++;
                            if ($audited['category'] !== null) {
                                $counts['ai_category']++;
                            }
                        } else {
                            $content->content_type = ContentKind::NonMusic;
                            $content->content_category = null;
                            $content->content_category_detail = null;
                            $counts['ai_non_music']++;
                        }

                        $content->save();
                    } else {
                        $counts['unresolved']++;
                    }
                } elseif ($this->needsAi($content)) {
                    $counts['unresolved']++;
                }

                $processed++;
            }
        });

        $this->table(
            array_keys($counts),
            [array_values($counts)],
        );
        $this->line("Processed {$processed} row(s).");

        return self::SUCCESS;
    }

    /**
     * Same completeness rule the audit enqueue uses: no kind at all, or music
     * without a sub-category.
     */
    private function needsAi(Content $content): bool
    {
        if ($content->content_type === null) {
            return true;
        }

        return $content->content_type === ContentKind::Music && $content->content_category === null;
    }
}
