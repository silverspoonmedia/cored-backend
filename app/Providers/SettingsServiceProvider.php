<?php

namespace App\Providers;

use App\Support\Settings\Repositories\Contracts\SystemSettingRepositoryInterface;
use App\Support\Settings\Repositories\Eloquent\SystemSettingRepository;
use App\Support\Settings\SettingsReader;
use App\Support\Settings\SettingsService;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SystemSettingRepositoryInterface::class, SystemSettingRepository::class);
        $this->app->singleton(SettingsReader::class);
        $this->app->singleton(SettingsService::class);
    }
}
