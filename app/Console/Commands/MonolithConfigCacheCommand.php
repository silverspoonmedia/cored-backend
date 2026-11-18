<?php

namespace App\Console\Commands;

use App\Support\Config\FreshConfigurationLoader;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\ConfigCacheCommand as LaravelConfigCacheCommand;

class MonolithConfigCacheCommand extends LaravelConfigCacheCommand
{
    public function __construct(
        Filesystem $files,
        private readonly FreshConfigurationLoader $freshLoader,
    ) {
        parent::__construct($files);
    }

    protected function getFreshConfiguration()
    {
        return $this->freshLoader->load($this->laravel);
    }
}
