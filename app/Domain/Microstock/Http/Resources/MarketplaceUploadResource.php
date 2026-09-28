<?php

namespace App\Domain\Microstock\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One marketplace upload attempt. No secret material and no local paths — the
 * owner sees the marketplace, the remote path, the counts and the failure
 * reason, which is what the collection status column needs.
 */
class MarketplaceUploadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'marketplace' => $this->marketplace,
            'status' => $this->status?->value,
            'remote_path' => $this->remote_path,
            'file_count' => $this->file_count,
            'byte_count' => $this->byte_count,
            'error' => $this->error,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
