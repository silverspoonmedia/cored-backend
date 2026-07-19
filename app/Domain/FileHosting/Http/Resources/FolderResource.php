<?php

namespace App\Domain\FileHosting\Http\Resources;

use App\Domain\FileHosting\Enums\Visibility;
use App\Domain\FileHosting\Services\VisibilityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\FileHosting\Models\Folder */
class FolderResource extends JsonResource
{
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
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
