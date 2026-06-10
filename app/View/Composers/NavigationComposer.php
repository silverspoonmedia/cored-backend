<?php

namespace App\View\Composers;

use App\Models\User;
use App\Services\Navigation\MenuService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Feeds the per-viewer navigation tree to the sidebar. Reuses the exact same
 * MenuService the API's navigation endpoint uses, so the web sidebar and the
 * API tree can never drift — single source of truth, fully RBAC-filtered.
 */
class NavigationComposer
{
    public function __construct(private readonly MenuService $menus) {}

    public function compose(View $view): void
    {
        $user = Auth::guard('api')->user();

        $view->with('navigation', $this->menus->navigationTree($user instanceof User ? $user : null));
    }
}
