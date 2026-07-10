<?php

namespace App\Domain\Navigation\Http\Resources;

use App\Domain\Navigation\Models\Menu;
use App\Domain\Navigation\Support\MenuAncestorChain;
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
            'ancestor_chain' => MenuAncestorChain::ancestors($this->resource),
            'ancestor_path' => MenuAncestorChain::path($this->resource),
        ]);
    }
}
