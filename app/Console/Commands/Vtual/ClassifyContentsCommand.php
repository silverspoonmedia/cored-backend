<?php

namespace App\Console\Commands\Vtual;

use App\Domain\Vtual\Enums\ContentKind;
use App\Domain\Vtual\Integrations\Ai\ContentAiClassifier;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Services\ContentCategoryClassifier;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'vtual:classify-contents')]
class ClassifyContentsCommand extends Command
{
    protected $signature = 'vtual:classify-contents
                            {--chunk=500 : Rows per chunk}
                            {--include-classified : Recompute already-classified rows too}
                            {--ai : Fill null sub-categories on music rows via the AI audit pass (requires configured endpoint/key)}';

    protected $description = 'Backfill content classification on hydrated YouTube contents (heuristic pass, optional AI audit pass with --ai)';

    public function handle(ContentCategoryClassifier $classifier, ContentAiClassifier $ai): int
    {
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
            $query->whereNull('content_category');
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Nothing to classify.');

            return self::SUCCESS;
        }

        $this->info("Classifying {$total} row(s)…");

        $counts = array_fill_keys(['cover', 'original', 'singing_stream', 'ai_cover', 'ai_original', 'ai_singing_stream', 'unknown'], 0);
        $processed = 0;

        $query->chunkById($chunk, function ($rows) use ($classifier, $ai, $withAi, &$counts, &$processed): void {
            foreach ($rows as $content) {
                $category = $classifier->classifyStored([
                    'title' => $content->title,
                    'description' => $content->description,
                    'duration_seconds' => $this->storedDurationSeconds($content),
                    'type' => $content->type?->value ?? (string) $content->type,
                ]);

                if ($category !== null) {
                    $content->content_category = $category;
                    $content->save();
                    $counts[$category->value] = ($counts[$category->value] ?? 0) + 1;
                } elseif ($withAi && $content->content_type === ContentKind::Music) {
                    $audited = $ai->classifyMusicContent([
                        'external_id' => $content->external_id,
                        'title' => $content->title,
                        'description' => $content->description,
                        'duration_seconds' => $this->storedDurationSeconds($content),
                    ]);

                    if ($audited !== null) {
                        $content->content_category = $audited['category'];
                        $content->content_category_detail = $audited['detail'];
                        $content->save();
                        $counts['ai_'.$audited['category']->value] = ($counts['ai_'.$audited['category']->value] ?? 0) + 1;
                    } else {
                        $counts['unknown']++;
                    }
                } else {
                    $counts['unknown']++;
                }

                $processed++;
            }
        });

        $this->table(
            ['cover', 'original', 'singing_stream', 'ai_cover', 'ai_original', 'ai_singing_stream', 'unknown', 'processed'],
            [[
                $counts['cover'],
                $counts['original'],
                $counts['singing_stream'],
                $counts['ai_cover'],
                $counts['ai_original'],
                $counts['ai_singing_stream'],
                $counts['unknown'],
                $processed,
            ]],
        );

        return self::SUCCESS;
    }

    private function storedDurationSeconds(Content $content): ?int
    {
        if ($content->started_at !== null && $content->ended_at !== null) {
            return (int) $content->ended_at->diffInSeconds($content->started_at);
        }

        return null;
    }
}
