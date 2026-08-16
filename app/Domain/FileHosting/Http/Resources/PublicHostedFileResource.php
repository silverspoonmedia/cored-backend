<?php

namespace App\Domain\FileHosting\Http\Resources;

use App\Domain\FileHosting\Models\HostedFile;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HostedFile */
class PublicHostedFileResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * Guest-facing file payload. Storage topology and owner identifiers stay off this shape.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            ...$this->byteFields('size', $this->size),
            'mime' => $this->mime,
            'visibility' => $this->visibility,
        ];
    }
}
