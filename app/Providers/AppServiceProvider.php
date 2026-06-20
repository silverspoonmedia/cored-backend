<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Events\ApprovalCompleted;
use App\Listeners\SendApprovalCompletedNotification;
use App\Listeners\UpdateDocumentStatus;
use App\Repositories\Contracts\ApprovalRepositoryInterface;
use App\Repositories\Contracts\EloquentCrudRepositoryInterface;
use App\Repositories\Contracts\MenuRepositoryInterface;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\ApprovalRepository;
use App\Repositories\Eloquent\EloquentCrudRepository;
use App\Repositories\Eloquent\MenuRepository;
use App\Repositories\Eloquent\NotificationRepository;
use App\Repositories\Eloquent\OrderRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Support\DataTable\DataTableResponseFactory;
use App\Support\Web\WebLayout;
use App\View\Composers\NavigationComposer;
use App\View\Composers\NotificationBellComposer;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EloquentCrudRepositoryInterface::class, EloquentCrudRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(MenuRepositoryInterface::class, MenuRepository::class);
        $this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->bind(ApprovalRepositoryInterface::class, ApprovalRepository::class);
        $this->app->singleton(DataTableResponseFactory::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);

        // Register event listeners
        Event::listen(ApprovalCompleted::class, UpdateDocumentStatus::class);
        Event::listen(ApprovalCompleted::class, SendApprovalCompletedNotification::class);

        // The navigation snapshot caches role names. Renaming or deleting a role
        // would otherwise leave the cached gate referencing a stale name.
        $flushMenuCache = fn () => $this->app->make(MenuRepositoryInterface::class)->flushNavigationCache();
        Role::updated($flushMenuCache);
        Role::deleted($flushMenuCache);

        Blade::anonymousComponentPath(WebLayout::componentsPath(), WebLayout::name());

        // The sidebar and navbar partials render layout-scoped navigation trees.
        $navigation = NavigationComposer::class;
        View::composer(WebLayout::sidebarComposerView(), function (\Illuminate\View\View $view) use ($navigation): void {
            app($navigation)->composeSidebar($view);
        });
        View::composer(WebLayout::navbarComposerView(), function (\Illuminate\View\View $view) use ($navigation): void {
            app($navigation)->composeNavbar($view);
            app(NotificationBellComposer::class)->compose($view);
        });
    }
}
