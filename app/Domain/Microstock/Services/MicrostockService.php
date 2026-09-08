<?php

namespace App\Domain\Microstock\Services;

use App\Domain\Archivd\Enums\WorkspaceKind;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Models\Workspace;
use App\Domain\Archivd\Services\WorkspaceAccessService;
use App\Domain\Archivd\Services\WorkspaceService;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\BillingSource;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Exceptions\MicrostockException;
use App\Domain\Microstock\Jobs\AnalyzeAssetVision;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\AnalysisStageService;
use App\Domain\Service\Services\ServiceEntitlementService;
use Illuminate\Support\Str;

/**
 * Microstock core: system workspace resolution + asset metadata lifecycle.
 * Storage/upload/browse reuse Archivd; this layer owns the
 * product-specific metadata (AI title/keywords) on top of hosted files.
 */
class MicrostockService
{
    public function __construct(
        private readonly WorkspaceService $workspaces,
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly ServiceEntitlementService $entitlements,
        private readonly AiBillingService $billing,
    ) {}

    /**
     * The owner's built-in Microstock system workspace (lazy-created).
     *
     * Gated by Microstock activation: the owner must hold an active Microstock
     * workspace-slot entitlement (free registration or slot addon) purchased
     * via /services. Provisioning beyond the slot limit is rejected.
     */
    public function workspaceFor(User $owner): Workspace
    {
        if (! $this->entitlements->hasActiveMicrostockWorkspaceSlot($owner->id)) {
            throw new MicrostockException(__('api/microstock.workspace_not_activated'), 403);
        }

        return $this->workspaces->systemWorkspaceFor($owner, WorkspaceKind::Microstock);
    }

    /**
     * Create an additional Microstock workspace when the owner's standalone
     * slot limit (free base + compounding slot addon) allows it.
     */
    public function createWorkspace(User $owner, ?string $name = null): Workspace
    {
        if (! $this->entitlements->hasActiveMicrostockWorkspaceSlot($owner->id)) {
            throw new MicrostockException(__('api/microstock.workspace_not_activated'), 403);
        }

        return $this->workspaces->createSystemWorkspace($owner, WorkspaceKind::Microstock, $name);
    }

    /**
     * Slot availability snapshot for the owner (no provisioning side effects).
     *
     * @return array{activated: bool, used: int, limit: int, can_create: bool}
     */
    public function slotStatus(User $owner): array
    {
        $activated = $this->entitlements->hasActiveMicrostockWorkspaceSlot($owner->id);
        $limit = $this->workspaceAccess->microstockSlotLimit($owner->id);
        $used = $this->workspaceAccess->countActiveOwnedMicrostock($owner->id);

        return [
            'activated' => $activated,
            'used' => $used,
            'limit' => $limit,
            'can_create' => $activated && $used < $limit,
        ];
    }

    /**
     * Resolve a hosted file the actor may analyze, scoped to the Microstock
     * system workspace of the given owner.
     */
    public function resolveAsset(User $actor, string $fileId): HostedFile
    {
        $file = HostedFile::query()->find($fileId);

        if ($file === null) {
            throw new MicrostockException(__('api/microstock.asset_not_found'), 404);
        }

        $workspace = $file->workspace;

        if ($workspace === null || $workspace->kind !== WorkspaceKind::Microstock) {
            throw new MicrostockException(__('api/microstock.asset_not_found'), 404);
        }

        $this->workspaceAccess->assertCanMutate($workspace, $actor);

        return $file;
    }

    /**
     * Queue AI analysis for an asset; creates/reset the metadata row.
     */
    public function requestAnalysis(User $actor, string $fileId): AssetMetadata
    {
        $file = $this->resolveAsset($actor, $fileId);

        $plan = $this->billing->planAnalyze($actor, 1);

        $analysisRunId = (string) Str::uuid();

        $metadata = AssetMetadata::query()->firstOrNew(['file_id' => $file->id]);
        $metadata->user_id = $file->user_id;
        $source = $plan->dailySlots > 0 ? BillingSource::DailyFree : BillingSource::Prepaid;
        app(AnalysisStageService::class)->initializeStages(
            $metadata,
            (string) $actor->id,
            $analysisRunId,
            $source,
        );

        $file->loadMissing('folder');
        $hint = $file->folder?->collection_hint;
        $folderId = $file->folder_id !== null ? (string) $file->folder_id : null;

        AnalyzeAssetVision::dispatchForSolo((string) $metadata->id, $hint, $folderId);

        return $metadata;
    }

    public function metadataFor(User $actor, string $fileId): AssetMetadata
    {
        $file = $this->resolveAsset($actor, $fileId);

        $metadata = AssetMetadata::query()->where('file_id', $file->id)->first();

        if ($metadata === null) {
            throw new MicrostockException(__('api/microstock.metadata_not_found'), 404);
        }

        return $metadata;
    }

    /**
     * Final manual curation: persist the user-edited title/keywords.
     *
     * @param  list<string>  $keywords
     */
    public function finalize(User $actor, string $fileId, string $title, array $keywords): AssetMetadata
    {
        $metadata = $this->metadataFor($actor, $fileId);

        $title = trim($title);
        if ($title === '') {
            throw new MicrostockException(__('api/microstock.title_required'), 422);
        }

        $keywords = array_values(array_filter(array_map(
            static fn ($k): string => is_string($k) ? trim($k) : '',
            $keywords,
        ), static fn (string $k): bool => $k !== ''));

        $metadata->forceFill([
            'title' => $title,
            'keywords' => $keywords,
            'status' => MetadataStatus::Finalized,
        ])->save();

        return $metadata->refresh();
    }
}
