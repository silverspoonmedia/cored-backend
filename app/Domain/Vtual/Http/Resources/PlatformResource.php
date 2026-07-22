<?php

namespace App\Domain\Vtual\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Vtual\Models\Platform */
class PlatformResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key instanceof \BackedEnum ? $this->key->value : $this->key,
            'name' => $this->name,
            'enabled' => (bool) $this->enabled,
            'supports_live' => (bool) $this->supports_live,
            'supports_vod' => (bool) $this->supports_vod,
        ];
    }
}
