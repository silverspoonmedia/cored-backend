<?php

namespace App\Domain\Vtual\Http\Resources;

use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Vtual\Models\Content */
class ContentResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'type' => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'title' => $this->title,
            'thumbnail_url' => $this->thumbnail_url,
            'url' => $this->url,
            ...$this->dateFields('published_at', $this->published_at),
            'platform' => $this->whenLoaded('platform', fn () => new PlatformResource($this->platform)),
            'channel' => $this->whenLoaded('channel', fn (): array => [
                'id' => $this->channel->id,
                'handle' => $this->channel->handle,
                'display_name' => $this->channel->display_name,
            ]),
        ];
    }
}
