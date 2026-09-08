<?php

namespace App\Domain\Microstock\Jobs;

use App\Domain\Archivd\Enums\CollectionStatus;
use App\Domain\Archivd\Models\Folder;
use App\Domain\Microstock\Enums\AnalysisPipeline;
use App\Domain\Microstock\Enums\AnalysisStageStatus;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\AnalysisStageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Single authority that declares stalled AI analysis failed. With decoupled
 * stages, grace deadlines are measured per stage from started_at (vision or
 * thinking) with a legacy fallback on metadata.created_at. Also re-dispatches
 * thinking jobs when vision completed but thinking was never queued.
 */
class AnalysisWatchdog implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public int $backoff = 30;

    public function handle(AnalysisStageService $stages): void
    {
        $this->failExpiredStages($stages);
        $this->dispatchThinkingBacklog();
        $this->completeProcessingFolders($stages);

        if ($this->hasProcessingCollections()) {
            static::dispatch()->delay(now()->addSeconds(60));
        }
    }

    private function failExpiredStages(AnalysisStageService $stages): void
    {
        $visionGrace = (int) setting_config('microstock.ai.stage.vision_grace_after', 600);
        $thinkingGrace = (int) setting_config('microstock.ai.stage.thinking_grace_after', 1800);
        $legacyGrace = (int) setting_config('microstock.ai.phase.grace_after', 1500);

        AssetMetadata::query()
            ->where('status', MetadataStatus::Pending->value)
            ->each(function (AssetMetadata $metadata) use ($stages, $visionGrace, $thinkingGrace, $legacyGrace): void {
                $visionStatus = $metadata->ai_vision['status'] ?? AnalysisStageStatus::Pending->value;
                $thinkingStatus = $metadata->ai_thinking['status'] ?? AnalysisStageStatus::Pending->value;

                if ($visionStatus === AnalysisStageStatus::Running->value) {
                    $startedAt = $metadata->ai_vision['started_at'] ?? null;
                    if ($this->isPastGrace($startedAt, $metadata->created_at, $visionGrace)) {
                        $stages->markVisionFailed($metadata, 'Vision stage exceeded the grace period');
                    }

                    return;
                }

                if ($visionStatus === AnalysisStageStatus::Ok->value
                    && in_array($thinkingStatus, [AnalysisStageStatus::Pending->value, AnalysisStageStatus::Running->value], true)) {
                    $startedAt = $thinkingStatus === AnalysisStageStatus::Running->value
                        ? ($metadata->ai_thinking['started_at'] ?? null)
                        : ($metadata->ai_vision['completed_at'] ?? null);

                    if ($this->isPastGrace($startedAt, $metadata->created_at, $thinkingGrace)) {
                        $stages->markThinkingFailed($metadata, 'Thinking stage exceeded the grace period');
                    }

                    return;
                }

                if ($visionStatus === AnalysisStageStatus::Pending->value
                    && $thinkingStatus === AnalysisStageStatus::Pending->value
                    && $metadata->created_at !== null
                    && $metadata->created_at->lt(now()->subSeconds($legacyGrace))) {
                    $stages->markVisionFailed($metadata, 'Analysis exceeded the grace period');
                }
            });
    }

    private function dispatchThinkingBacklog(): void
    {
        $backlogAfter = (int) setting_config('microstock.ai.stage.thinking_backlog_after', 120);
        $cutoff = now()->subSeconds($backlogAfter);

        AssetMetadata::query()
            ->with('file.folder')
            ->where('status', MetadataStatus::Pending->value)
            ->lazyById()
            ->each(function (AssetMetadata $metadata) use ($cutoff): void {
                $vision = is_array($metadata->ai_vision) ? $metadata->ai_vision : [];
                $thinking = is_array($metadata->ai_thinking) ? $metadata->ai_thinking : [];

                if (($vision['status'] ?? '') !== AnalysisStageStatus::Ok->value) {
                    return;
                }

                if (($thinking['status'] ?? AnalysisStageStatus::Pending->value) !== AnalysisStageStatus::Pending->value) {
                    return;
                }

                $completedAt = $vision['completed_at'] ?? null;
                if ($completedAt === null) {
                    return;
                }

                if (\Illuminate\Support\Carbon::parse($completedAt)->isAfter($cutoff)) {
                    return;
                }

                $file = $metadata->file;
                if ($file === null) {
                    return;
                }

                $folderId = $file->folder_id !== null ? (string) $file->folder_id : null;
                $folder = $file->folder;
                $hint = $folder?->collection_hint;

                $pipeline = $folder !== null
                    && $folder->collection_status === CollectionStatus::Processing
                    ? AnalysisPipeline::Collection
                    : AnalysisPipeline::Solo;

                AnalyzeAssetThinking::dispatchForPipeline(
                    (string) $metadata->id,
                    $hint,
                    $folderId,
                    $pipeline,
                );
            });
    }

    private function isPastGrace(?string $startedAt, ?\Illuminate\Support\Carbon $fallbackCreatedAt, int $graceSeconds): bool
    {
        if ($startedAt !== null) {
            return \Illuminate\Support\Carbon::parse($startedAt)->lt(now()->subSeconds($graceSeconds));
        }

        if ($fallbackCreatedAt !== null) {
            return $fallbackCreatedAt->lt(now()->subSeconds($graceSeconds));
        }

        return false;
    }

    private function completeProcessingFolders(AnalysisStageService $stages): void
    {
        Folder::query()
            ->where('collection_status', CollectionStatus::Processing->value)
            ->each(function (Folder $folder) use ($stages): void {
                $stages->completeFolderIfReady((string) $folder->id);
            });
    }

    private function hasProcessingCollections(): bool
    {
        return Folder::query()
            ->where('collection_status', CollectionStatus::Processing->value)
            ->exists();
    }
}
