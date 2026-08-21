<?php

namespace App\Providers\Domain;

use App\Domain\Notification\Repositories\Contracts\NotificationRepositoryInterface;
use App\Domain\Notification\Repositories\Contracts\NotificationTypeSettingRepositoryInterface;
use App\Domain\Notification\Repositories\Eloquent\NotificationRepository;
use App\Domain\Notification\Repositories\Eloquent\NotificationTypeSettingRepository;
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
        // The Blade navbar bell composer is gone with the Blade app; the SPA
        // renders notifications from the API.
    }
}
