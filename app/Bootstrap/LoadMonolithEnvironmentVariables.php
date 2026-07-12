<?php

namespace App\Bootstrap;

use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * Always load the active `.env` file unless Artisan passes an explicit --env flag.
 *
 * Monolith stores profile snapshots as `.env.local`, `.env.staging`, and
 * `.env.production` for env:switch — the same names Laravel would auto-load when
 * APP_ENV is preset in the process. Snapshots must not replace the active `.env`
 * at bootstrap; env:switch copies the chosen snapshot into `.env` instead.
 */
class LoadMonolithEnvironmentVariables extends LoadEnvironmentVariables
{
    protected function checkForSpecificEnvironmentFile($app): void
    {
        if (! $app->runningInConsole()) {
            return;
        }

        $input = new ArgvInput;

        if ($input->hasParameterOption('--env')) {
            $this->setEnvironmentFilePath(
                $app,
                $app->environmentFile().'.'.$input->getParameterOption('--env'),
            );
        }
    }
}
