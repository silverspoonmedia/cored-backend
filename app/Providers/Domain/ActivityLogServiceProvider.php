<?php

namespace App\Providers\Domain;

use App\Domain\ActivityLog\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Domain\ActivityLog\Repositories\Eloquent\ActivityLogRepository;
use Illuminate\Support\ServiceProvider;

class ActivityLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ActivityLogRepositoryInterface::class, ActivityLogRepository::class);
    }
}
