<?php

namespace App\Console;

use App\Bootstrap\LoadMonolithEnvironmentVariables;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * @var array<int, class-string>
     */
    protected $bootstrappers = [
        LoadMonolithEnvironmentVariables::class,
        \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
        \Illuminate\Foundation\Bootstrap\HandleExceptions::class,
        \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
        \Illuminate\Foundation\Bootstrap\SetRequestForConsole::class,
        \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
        \Illuminate\Foundation\Bootstrap\BootProviders::class,
    ];

    /**
     * Laravel only auto-discovers command paths on the stock Console kernel class.
     */
    protected function shouldDiscoverCommands(): bool
    {
        return true;
    }
}
