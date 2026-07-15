<?php

namespace App\Domain\FileHosting\Http\Resources;

use App\Domain\FileHosting\Enums\Visibility;
use App\Domain\FileHosting\Services\VisibilityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\FileHosting\Models\HostedFile */
class HostedFileResource extends JsonResource
{
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
            'folder_id' => $this->folder_id,
            'name' => $this->name,
            'disk' => $this->disk,
            'path' => $this->path,
            'size' => $this->size,
            'mime' => $this->mime,
            'checksum' => $this->checksum,
            'visibility' => $visibility,
            'share_url' => $shareUrl,
            'download_count' => (int) $this->download_count,
            'view_count' => (int) $this->view_count,
            'last_downloaded_at' => $this->last_downloaded_at?->toIso8601String(),
            'last_viewed_at' => $this->last_viewed_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
