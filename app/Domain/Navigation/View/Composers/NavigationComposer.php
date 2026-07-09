<?php

namespace App\Domain\Navigation\View\Composers;

use App\Domain\Identity\Models\User;
use App\Domain\Navigation\Services\MenuService;
use App\Domain\Navigation\Support\MenuLayout;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Feeds per-layout navigation trees to the sidebar and navbar partials. Reuses
 * the same MenuService the API uses, scoped by layout identifier.
 */
class NavigationComposer
{
    public function __construct(private readonly MenuService $menus) {}

    public function composeSidebar(View $view): void
    {
        $view->with('navigation', $this->tree(MenuLayout::SIDEBAR));
    }

    public function composeNavbar(View $view): void
    {
        $view->with('navbarNavigation', $this->tree(MenuLayout::NAVBAR));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tree(string $layout): array
    {
        $user = Auth::guard('api')->user();

        return $this->menus->navigationTree($user instanceof User ? $user : null, layout: $layout);
    }
}
