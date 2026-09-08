<?php

namespace App\Domain\Archivd\Http\Resources;

use App\Domain\Archivd\Enums\Visibility;
use App\Domain\Archivd\Models\Folder;
use App\Domain\Archivd\Services\VisibilityService;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Folder */
class FolderResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $visibility = (string) ($this->visibility ?: Visibility::Private->value);
        $shareUrl = $visibility === Visibility::AnyoneWithLink->value
            ? app(VisibilityService::class)->folderShareUrl($this->resource)
            : null;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'workspace_id' => $this->workspace_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'order' => $this->order,
            'visibility' => $visibility,
            'share_url' => $shareUrl,
            'collection_status' => $this->collection_status?->value,
            'collection_hint' => $this->collection_hint,
            'collection_locked' => $this->isCollectionLocked(),
            ...$this->dateFields('deleted_at', $this->deleted_at),
            ...$this->dateFields('created_at', $this->created_at),
            ...$this->dateFields('updated_at', $this->updated_at),
        ];
    }
}
