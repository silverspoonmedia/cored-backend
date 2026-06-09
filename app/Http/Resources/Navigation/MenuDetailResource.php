<?php

namespace App\Http\Resources\Navigation;

use App\Models\Menu;
use Illuminate\Http\Request;

/**
 * @mixin Menu
 */
class MenuDetailResource extends MenuListResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ]);
    }
}
