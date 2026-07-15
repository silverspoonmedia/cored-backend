<?php

namespace App\Domain\FileHosting\Http\Resources;

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
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
