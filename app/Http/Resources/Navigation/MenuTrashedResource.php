<?php

namespace App\Http\Resources\Navigation;

use App\Models\Menu;
use Illuminate\Http\Request;

/**
 * @mixin Menu
 */
class MenuTrashedResource extends MenuListResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ]);
    }
}
