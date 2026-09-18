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
 * the program pass here can only use title / description / duration.
 *
 * `--ai` is the escape hatch for rows the program pass still cannot decide —
 * ambiguous short-form videos, and music rows without a sub-category. It writes
 * both layers, exactly like the `vtual:audit-content-ai` drainer.
 */
#[AsCommand(name: 'vtual:classify-contents')]
class ClassifyContentsCommand extends Command
{
    protected $signature = 'vtual:classify-contents
                            {--chunk=500 : Rows per chunk}
                            {--include-classified : Recompute already-classified rows too}
                            {--ai : Resolve rows the program pass cannot decide via the AI audit pass (requires configured endpoint/key)}';

    protected $description = 'Backfill content classification on hydrated YouTube contents (program pass, optional AI pass with --ai)';

    public function handle(
        ContentCategoryClassifier $classifier,
        ContentKindClassifier $kinds,
        ContentAiClassifier $ai,
    ): int {
        $chunk = max(1, (int) $this->option('chunk'));
        $includeClassified = (bool) $this->option('include-classified');
        $withAi = (bool) $this->option('ai');

        if ($withAi && ! $ai->enabled()) {
            $this->error('AI audit is not enabled/configured (vtual.content_classification.ai.* + a bound AI connection).');

            return self::FAILURE;
        }

        $query = Content::query()
            ->whereHas('platform', fn ($q) => $q->where('key', 'youtube'))
            ->whereNotNull('hydrated_at')
            ->orderBy('id');

        if (! $includeClassified) {
            // Incomplete-row driven: work is needed when either layer is missing.
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
            'unresolved',
        ], 0);
        $processed = 0;

        $query->chunkById($chunk, function ($rows) use ($classifier, $kinds, $ai, $withAi, &$counts, &$processed): void {
            foreach ($rows as $content) {
                $video = [
                    'external_id' => $content->external_id,
                    'title' => $content->title,
                    'description' => $content->description,
                    'duration_seconds' => $content->durationSeconds(),
                    'type' => $content->type?->value ?? (string) $content->type,
                ];

                $dirty = false;

                // Layer 1 — music / non-music (program signals only).
                if ($content->content_type === null) {
                    $kind = $kinds->kind($video);
                    if ($kind !== null) {
                        $content->content_type = $kind;
                        $counts['kind_'.$kind->value]++;
                        $dirty = true;
                    }
                }

                // Layer 2 — sub-category heuristics.
                if ($content->content_category === null) {
                    $category = $classifier->classifyStored($video);
                    if ($category !== null) {
                        $content->content_category = $category;
                        $counts[$category->value] = ($counts[$category->value] ?? 0) + 1;
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
