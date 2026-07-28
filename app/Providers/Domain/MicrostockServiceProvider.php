<?php

namespace App\Providers\Domain;

use App\Domain\Microstock\Contracts\AiMetadataService;
use App\Domain\Microstock\Services\HttpAiMetadataService;
use Illuminate\Support\ServiceProvider;

class MicrostockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiMetadataService::class, HttpAiMetadataService::class);
    }
}
