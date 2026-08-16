<?php

namespace App\Domain\FileHosting\Http\Resources;

use App\Domain\FileHosting\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Folder */
class PublicFolderResource extends JsonResource
{
    /**
     * Guest-facing folder payload. Owner identifiers stay off this shape.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
        ];
    }
}
