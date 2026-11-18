<?php

namespace App\Domain\Archivd\Http\Resources;

use App\Domain\Archivd\Models\ArchivdHashBlocklist;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ArchivdHashBlocklist */
class HashBlocklistResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sha256' => $this->sha256,
            'reason' => $this->reason,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', function () {
                $creator = $this->creator;

                return [
                    'id' => $creator->id,
                    'name' => $creator->name,
                    'email' => $creator->email,
                ];
            }),
            ...$this->dateFields('created_at', $this->created_at),
            ...$this->dateFields('updated_at', $this->updated_at),
        ];
    }
}
