<?php

namespace App\Domain\Microstock\Support;

use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\Microstock\Enums\AnalysisStageStatus;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Events\AssetMetadataProgress;
use App\Domain\Microstock\Events\CollectionAnalysisProgress;
use App\Domain\Microstock\Events\MicrostockBillingChanged;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\CollectionService;
use App\Support\Broadcasting\BroadcastConnection;
use Illuminate\Support\Facades\Cache;

/**
 * Broadcast Microstock AI analysis progress over File Hosting websocket channels.
 */
class MicrostockRealtime
{
    private const THROTTLE_SECONDS = 1;

    public function __construct(
        private readonly CollectionService $collections,
    ) {}

    public function enabled(): bool
    {
        return BroadcastConnection::isConfigured();
    }

    public function collectionProgress(Folder $folder, bool $force = false): void
    {
        if (! $this->enabled()) {
            return;
        }

        $folderId = (string) $folder->id;

        if (! $force && ! Cache::add("microstock:rt:collection:{$folderId}", 1, self::THROTTLE_SECONDS)) {
            return;
        }

        $folder = $folder->refresh();
        $snapshot = $this->collections->statusSnapshot($folder);

        event(new CollectionAnalysisProgress(
            folderId: $folderId,
            ownerUserId: (string) $folder->user_id,
            workspaceId: $folder->workspace_id,
            snapshot: $snapshot,
        ));
    }

    public function assetProgress(AssetMetadata $metadata, ?HostedFile $file = null, bool $force = false): void
    {
        if (! $this->enabled()) {
            return;
        }

        $metadata = $metadata->refresh();

        if (! $force && ! Cache::add("microstock:rt:asset:{$metadata->id}", 1, self::THROTTLE_SECONDS)) {
            return;
        }

        $file ??= $metadata->file;

        if ($file === null) {
            return;
        }

        event(new AssetMetadataProgress(
            fileId: (string) $file->id,
            folderId: $file->folder_id,
            ownerUserId: (string) $file->user_id,
            workspaceId: $file->workspace_id,
            microstock: $this->microstockPayload($metadata),
        ));
    }

    public function billingChanged(string $ownerUserId, string $action): void
    {
        if (! $this->enabled()) {
            return;
        }

        event(new MicrostockBillingChanged($ownerUserId, $action));
    }

    public function notifyAssetAndCollection(AssetMetadata $metadata, bool $forceCollection = false): void
    {
        $file = $metadata->relationLoaded('file')
            ? $metadata->file
            : $metadata->file()->first();

        if ($file === null) {
            return;
        }

        $this->assetProgress($metadata, $file);

        if ($file->folder_id !== null) {
            $folder = $file->relationLoaded('folder')
                ? $file->folder
                : Folder::query()->find($file->folder_id);

            if ($folder !== null) {
                $this->collectionProgress($folder, $forceCollection);
            }
        }
    }

    /**
     * @return array{title: string|null, keywords: list<string>, status: string|null, stages: array<string, string>, phase: string|null, error: string|null}
     */
    public function microstockPayload(AssetMetadata $metadata): array
    {
        $status = $metadata->status;

        return [
            'title' => $metadata->title,
            'keywords' => $metadata->keywords ?? [],
            'status' => $status?->value,
            'stages' => $this->stageSummary($metadata),
            'phase' => $status === MetadataStatus::Pending && $metadata->created_at !== null
                ? CollectionService::phaseFor($metadata->created_at)
                : $status?->value,
            'error' => $metadata->error,
        ];
    }

    /**
     * @return array{vision: string, thinking: string, result: string}
     */
    private function stageSummary(AssetMetadata $metadata): array
    {
        return [
            'vision' => $metadata->ai_vision['status'] ?? AnalysisStageStatus::Pending->value,
            'thinking' => $metadata->ai_thinking['status'] ?? AnalysisStageStatus::Pending->value,
            'result' => $metadata->ai_result['status'] ?? AnalysisStageStatus::Pending->value,
        ];
    }
}
