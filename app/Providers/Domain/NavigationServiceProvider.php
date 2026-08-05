<?php

namespace App\Providers\Domain;

use App\Domain\Identity\Models\Role;
use App\Domain\Navigation\Listeners\ForgetPresentedNavigationTree;
use App\Domain\Navigation\Repositories\Contracts\MenuRepositoryInterface;
use App\Domain\Navigation\Repositories\Eloquent\MenuRepository;
use App\Domain\Navigation\View\Composers\NavigationComposer;
use App\Support\Product\Events\ProductActivationStateChanged;
use App\Support\Web\WebLayout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class NavigationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MenuRepositoryInterface::class, MenuRepository::class);
    }

    public function boot(): void
    {
        // The navigation snapshot caches role names. Renaming or deleting a role
        // would otherwise leave the cached gate referencing a stale name.
        $flushMenuCache = fn () => $this->app->make(MenuRepositoryInterface::class)->flushNavigationCache();
        Role::updated($flushMenuCache);
        Role::deleted($flushMenuCache);

        Event::listen(ProductActivationStateChanged::class, ForgetPresentedNavigationTree::class);

        // The sidebar and navbar partials render layout-scoped navigation trees.
        $navigation = NavigationComposer::class;
        View::composer(WebLayout::sidebarComposerView(), function (\Illuminate\View\View $view) use ($navigation): void {
            app($navigation)->composeSidebar($view);
        });
        View::composer(WebLayout::navbarComposerView(), function (\Illuminate\View\View $view) use ($navigation): void {
            app($navigation)->composeNavbar($view);
        });
    }
}
