<?php

namespace App\Domain\FileHosting\Http\Resources;

use App\Domain\FileHosting\Enums\Visibility;
use App\Domain\FileHosting\Enums\WorkspaceKind;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\HashBlocklistService;
use App\Domain\FileHosting\Services\VisibilityService;
use App\Domain\FileHosting\Support\RegionResolver;
use App\Domain\Microstock\Services\AnalysisStageService;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HostedFile */
class HostedFileResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $visibility = (string) ($this->visibility ?: Visibility::Private->value);
        $shareUrl = $visibility === Visibility::AnyoneWithLink->value
            ? app(VisibilityService::class)->fileShareUrl($this->resource)
            : null;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'workspace_id' => $this->workspace_id,
            'workspace' => $this->whenLoaded('workspace', fn () => $this->workspace === null ? null : [
                'id' => $this->workspace->id,
                'kind' => $this->workspace->kind->value,
                'name' => $this->workspace->name,
            ]),
            'created_by' => $this->created_by,
            'folder_id' => $this->folder_id,
            'name' => $this->name,
            'disk' => $this->when($this->viewerMaySeeStorage($request), $this->disk),
            'region' => $this->when($this->viewerMaySeeStorage($request), RegionResolver::keyForDisk($this->disk)),
            'path' => $this->when($this->viewerMaySeeStorage($request), $this->path),
            ...$this->byteFields('size', $this->size),
            'mime' => $this->mime,
            'checksum' => $this->when($this->viewerMaySeeStorage($request), $this->checksum),
            'is_hash_blocked' => app(HashBlocklistService::class)->isFileBlocked($this->resource),
            'visibility' => $visibility,
            'share_url' => $shareUrl,
            'microstock' => $this->when(
                $this->resource->relationLoaded('workspace') && $this->resource->workspace?->kind === WorkspaceKind::Microstock,
                fn () => $this->whenLoaded('microstockMetadata', function () {
                    if ($this->microstockMetadata === null) {
                        return null;
                    }

                    $stages = app(AnalysisStageService::class)->stageSummary($this->microstockMetadata);

                    return [
                        'title' => $this->microstockMetadata->title,
                        'keywords' => $this->microstockMetadata->keywords ?? [],
                        'status' => $this->microstockMetadata->status?->value,
                        'stages' => $stages,
                        'phase' => $this->microstockMetadata->status === \App\Domain\Microstock\Enums\MetadataStatus::Pending && $this->microstockMetadata->created_at !== null
                            ? \App\Domain\Microstock\Services\CollectionService::phaseFor($this->microstockMetadata->created_at)
                            : $this->microstockMetadata->status?->value,
                    ];
                }),
            ),
            'download_count' => (int) $this->download_count,
            'view_count' => (int) $this->view_count,
            ...$this->dateFields('last_downloaded_at', $this->last_downloaded_at),
            ...$this->dateFields('last_viewed_at', $this->last_viewed_at),
            ...$this->dateFields('deleted_at', $this->deleted_at),
            ...$this->dateFields('created_at', $this->created_at),
            ...$this->dateFields('updated_at', $this->updated_at),
        ];
    }

    private function viewerMaySeeStorage(Request $request): bool
    {
        $actor = $request->user();

        if ($actor === null) {
            return false;
        }

        if ($actor->can('view any file hosting resource') || $actor->can('manage any file hosting resource')) {
            return true;
        }

        return (string) $actor->getAuthIdentifier() === (string) $this->user_id;
    }
}
