<?php

namespace App\Providers;

use App\Domain\Approval\Events\ApprovalCompleted;
use App\Domain\Approval\Listeners\SendApprovalCompletedNotification;
use App\Domain\Approval\Listeners\UpdateDocumentStatus;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Policies\UserPolicy;
use App\Domain\ActivityLog\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Domain\Approval\Repositories\Contracts\ApprovalRepositoryInterface;
use App\Repositories\Contracts\EloquentCrudRepositoryInterface;
use App\Domain\Navigation\Repositories\Contracts\MenuRepositoryInterface;
use App\Domain\Notification\Repositories\Contracts\NotificationRepositoryInterface;
use App\Domain\Notification\Repositories\Contracts\NotificationTypeSettingRepositoryInterface;
use App\Domain\Playground\Repositories\Contracts\OrderRepositoryInterface;
use App\Domain\Identity\Repositories\Contracts\UserRepositoryInterface;
use App\Domain\ActivityLog\Repositories\Eloquent\ActivityLogRepository;
use App\Domain\Approval\Repositories\Eloquent\ApprovalRepository;
use App\Repositories\Eloquent\EloquentCrudRepository;
use App\Domain\Navigation\Repositories\Eloquent\MenuRepository;
use App\Domain\Notification\Repositories\Eloquent\NotificationRepository;
use App\Domain\Notification\Repositories\Eloquent\NotificationTypeSettingRepository;
use App\Domain\Playground\Repositories\Eloquent\OrderRepository;
use App\Domain\Identity\Repositories\Eloquent\UserRepository;
use App\Support\DataTable\DataTableResponseFactory;
use App\Support\Web\WebLayout;
use App\Domain\Navigation\View\Composers\NavigationComposer;
use App\Domain\Notification\View\Composers\NotificationBellComposer;
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
        $this->app->bind(NotificationTypeSettingRepositoryInterface::class, NotificationTypeSettingRepository::class);
        $this->app->bind(ActivityLogRepositoryInterface::class, ActivityLogRepository::class);
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

        // Auth subscribers (LogAuthenticationEvents, RecordAuthenticationActivity) use
        // subscribe() and are auto-discovered — do not Event::subscribe() them here
        // or every auth activity row is written twice.

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
