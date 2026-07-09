<?php

namespace App\Providers\Domain;

use App\Domain\Notification\Repositories\Contracts\NotificationRepositoryInterface;
use App\Domain\Notification\Repositories\Contracts\NotificationTypeSettingRepositoryInterface;
use App\Domain\Notification\Repositories\Eloquent\NotificationRepository;
use App\Domain\Notification\Repositories\Eloquent\NotificationTypeSettingRepository;
use App\Domain\Notification\View\Composers\NotificationBellComposer;
use App\Support\Web\WebLayout;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);
        $this->app->bind(NotificationTypeSettingRepositoryInterface::class, NotificationTypeSettingRepository::class);
    }

    public function boot(): void
    {
        // Chained onto the same hashed navbar view as NavigationComposer.
        View::composer(WebLayout::navbarComposerView(), function (\Illuminate\View\View $view): void {
            app(NotificationBellComposer::class)->compose($view);
        });
    }
}
