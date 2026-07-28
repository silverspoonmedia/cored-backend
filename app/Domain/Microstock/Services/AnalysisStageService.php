<?php

namespace App\Domain\Microstock\Services;

use App\Domain\FileHosting\Enums\CollectionStatus;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\Microstock\Enums\AnalysisStageStatus;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Models\AssetMetadata;
use Throwable;

class AnalysisStageService
{
    public function __construct(
        private readonly AiBillingService $billing,
    ) {}

    public function initializeStages(AssetMetadata $metadata): void
    {
        $metadata->forceFill([
            'ai_vision' => $this->pendingWorkStage(),
            'ai_thinking' => $this->pendingWorkStage(),
            'ai_result' => $this->pendingResultStage(),
            'ai_raw' => null,
            'ai_billing' => null,
            'title' => null,
            'keywords' => null,
            'description' => null,
            'category' => null,
            'error' => null,
            'status' => MetadataStatus::Pending,
        ])->save();
    }

    public function markVisionRunning(AssetMetadata $metadata): void
    {
        $stage = $metadata->ai_vision ?? $this->pendingWorkStage();
        $stage['status'] = AnalysisStageStatus::Running->value;
        $stage['started_at'] = now()->toIso8601String();
        $stage['error'] = null;

        $metadata->forceFill([
            'ai_vision' => $stage,
            'status' => MetadataStatus::Pending,
        ])->save();
    }

    /**
     * @param  array{title: string, keywords: list<string>, description?: string, category?: string, raw: mixed, stage_metrics?: list<array<string, mixed>>}  $vision
     */
    public function markVisionOk(AssetMetadata $metadata, array $vision): void
    {
        $metrics = $vision['stage_metrics'] ?? [];

        $metadata->forceFill([
            'ai_vision' => [
                'status' => AnalysisStageStatus::Ok->value,
                'started_at' => ($metadata->ai_vision['started_at'] ?? null) ?: now()->toIso8601String(),
                'completed_at' => now()->toIso8601String(),
                'error' => null,
                'output' => [
                    'title' => $vision['title'],
                    'keywords' => $vision['keywords'],
                    'description' => $vision['description'] ?? '',
                    'category' => $vision['category'] ?? '',
                    'raw' => $vision['raw'] ?? null,
                ],
                'stage_metrics' => $metrics,
            ],
            'status' => MetadataStatus::Pending,
        ])->save();
    }

    public function markVisionFailed(AssetMetadata $metadata, Throwable|string $error): void
    {
        $message = $error instanceof Throwable ? $error->getMessage() : $error;

        $aiVision = array_merge($metadata->ai_vision ?? $this->pendingWorkStage(), [
            'status' => AnalysisStageStatus::Failed->value,
            'completed_at' => now()->toIso8601String(),
            'error' => mb_substr($message, 0, 1000),
            'output' => null,
        ]);

        $aiThinking = [
            'status' => AnalysisStageStatus::Skipped->value,
            'started_at' => null,
            'completed_at' => now()->toIso8601String(),
            'error' => null,
            'output' => null,
            'stage_metrics' => [],
        ];

        $aiResult = [
            'status' => AnalysisStageStatus::Failed->value,
            'source' => null,
            'completed_at' => now()->toIso8601String(),
            'error' => mb_substr($message, 0, 1000),
        ];

        $metadata->forceFill([
            'ai_vision' => $aiVision,
            'ai_thinking' => $aiThinking,
            'ai_result' => $aiResult,
            'status' => MetadataStatus::Failed,
            'error' => mb_substr($message, 0, 1000),
        ])->save();

        $metadata->forceFill([
            'ai_raw' => $this->aggregateAiRaw($metadata->refresh()),
        ])->save();
    }

    public function markThinkingRunning(AssetMetadata $metadata): void
    {
        $stage = $metadata->ai_thinking ?? $this->pendingWorkStage();
        $stage['status'] = AnalysisStageStatus::Running->value;
        $stage['started_at'] = now()->toIso8601String();
        $stage['error'] = null;

        $metadata->forceFill([
            'ai_thinking' => $stage,
            'status' => MetadataStatus::Pending,
        ])->save();
    }

    /**
     * @param  array{title: string, keywords: list<string>, description?: string, category?: string, raw: mixed, stage_metrics?: list<array<string, mixed>>}  $curated
     */
    public function markThinkingOk(AssetMetadata $metadata, array $curated): void
    {
        $metrics = $curated['stage_metrics'] ?? [];

        $metadata->forceFill([
            'ai_thinking' => [
                'status' => AnalysisStageStatus::Ok->value,
                'started_at' => ($metadata->ai_thinking['started_at'] ?? null) ?: now()->toIso8601String(),
                'completed_at' => now()->toIso8601String(),
                'error' => null,
                'output' => [
                    'title' => $curated['title'],
                    'keywords' => $curated['keywords'],
                    'description' => $curated['description'] ?? '',
                    'category' => $curated['category'] ?? '',
                    'raw' => $curated['raw'] ?? null,
                ],
                'stage_metrics' => $metrics,
            ],
        ])->save();
    }

    public function markThinkingFailed(AssetMetadata $metadata, Throwable|string $error): void
    {
        $message = $error instanceof Throwable ? $error->getMessage() : $error;

        $aiThinking = array_merge($metadata->ai_thinking ?? $this->pendingWorkStage(), [
            'status' => AnalysisStageStatus::Failed->value,
            'completed_at' => now()->toIso8601String(),
            'error' => mb_substr($message, 0, 1000),
            'output' => null,
        ]);

        $aiResult = [
            'status' => AnalysisStageStatus::Failed->value,
            'source' => null,
            'completed_at' => now()->toIso8601String(),
            'error' => mb_substr($message, 0, 1000),
        ];

        $metadata->forceFill([
            'ai_thinking' => $aiThinking,
            'ai_result' => $aiResult,
            'status' => MetadataStatus::Failed,
            'error' => mb_substr($message, 0, 1000),
            'title' => null,
            'keywords' => null,
            'description' => null,
            'category' => null,
        ])->save();

        $metadata->forceFill([
            'ai_raw' => $this->aggregateAiRaw($metadata->refresh()),
        ])->save();
    }

    /**
     * @param  array{title: string, keywords: list<string>, description?: string, category?: string, raw: mixed}  $curated
     */
    public function composeResultFromThinking(AssetMetadata $metadata, array $curated): void
    {
        $metadata->forceFill([
            'title' => $curated['title'],
            'keywords' => $curated['keywords'],
            'description' => $curated['description'] ?? null,
            'category' => $curated['category'] ?? null,
            'ai_result' => [
                'status' => AnalysisStageStatus::Ok->value,
                'source' => 'thinking',
                'completed_at' => now()->toIso8601String(),
                'error' => null,
            ],
            'status' => MetadataStatus::Generated,
            'error' => null,
            'ai_raw' => $this->aggregateAiRaw($metadata->refresh()),
        ])->save();

        $this->billing->settleSuccessfulAnalysis($metadata->refresh());
    }

    public function markResultFailed(AssetMetadata $metadata, Throwable|string $error): void
    {
        $message = $error instanceof Throwable ? $error->getMessage() : $error;
        $this->markThinkingFailed($metadata, $message);
    }

    public function isTerminal(AssetMetadata $metadata): bool
    {
        return in_array($metadata->status, [
            MetadataStatus::Generated,
            MetadataStatus::Finalized,
            MetadataStatus::Failed,
        ], true);
    }

    public function hasRunningStage(AssetMetadata $metadata): bool
    {
        $vision = $metadata->ai_vision['status'] ?? AnalysisStageStatus::Pending->value;
        $thinking = $metadata->ai_thinking['status'] ?? AnalysisStageStatus::Pending->value;

        return $vision === AnalysisStageStatus::Running->value
            || $thinking === AnalysisStageStatus::Running->value;
    }

    /**
     * @return array{vision: mixed, thinking: mixed, stage_metrics: list<array<string, mixed>>}
     */
    public function aggregateAiRaw(AssetMetadata $metadata): array
    {
        $visionOutput = is_array($metadata->ai_vision) ? ($metadata->ai_vision['output'] ?? null) : null;
        $thinkingOutput = is_array($metadata->ai_thinking) ? ($metadata->ai_thinking['output'] ?? null) : null;

        return [
            'vision' => is_array($visionOutput) ? ($visionOutput['raw'] ?? null) : null,
            'thinking' => is_array($thinkingOutput) ? ($thinkingOutput['raw'] ?? null) : null,
            'stage_metrics' => array_values(array_merge(
                is_array($metadata->ai_vision) ? ($metadata->ai_vision['stage_metrics'] ?? []) : [],
                is_array($metadata->ai_thinking) ? ($metadata->ai_thinking['stage_metrics'] ?? []) : [],
            )),
        ];
    }

    public function stageSummary(AssetMetadata $metadata): array
    {
        return [
            'vision' => $metadata->ai_vision['status'] ?? AnalysisStageStatus::Pending->value,
            'thinking' => $metadata->ai_thinking['status'] ?? AnalysisStageStatus::Pending->value,
            'result' => $metadata->ai_result['status'] ?? AnalysisStageStatus::Pending->value,
        ];
    }

    public function completeFolderIfReady(string $folderId): void
    {
        $folder = Folder::query()->find($folderId);

        if ($folder === null || $folder->collection_status !== CollectionStatus::Processing) {
            return;
        }

        $fileIds = HostedFile::query()
            ->where('folder_id', $folder->id)
            ->pluck('id');

        if ($fileIds->isEmpty()) {
            return;
        }

        $rows = AssetMetadata::query()
            ->whereIn('file_id', $fileIds)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        foreach ($rows as $row) {
            if ($this->hasRunningStage($row) || ! $this->isTerminal($row)) {
                return;
            }
        }

        $hasSuccess = $rows->contains(fn (AssetMetadata $m): bool => in_array($m->status, [
            MetadataStatus::Generated,
            MetadataStatus::Finalized,
        ], true));

        $folder->markCollectionStatus(
            $hasSuccess ? CollectionStatus::Review : CollectionStatus::Failed,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingWorkStage(): array
    {
        return [
            'status' => AnalysisStageStatus::Pending->value,
            'started_at' => null,
            'completed_at' => null,
            'error' => null,
            'output' => null,
            'stage_metrics' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingResultStage(): array
    {
        return [
            'status' => AnalysisStageStatus::Pending->value,
            'source' => null,
            'completed_at' => null,
            'error' => null,
        ];
    }
}
