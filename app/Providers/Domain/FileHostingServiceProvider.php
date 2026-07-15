<?php

namespace App\Providers\Domain;

use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Policies\FolderPolicy;
use App\Domain\FileHosting\Policies\HostedFilePolicy;
use App\Domain\FileHosting\Repositories\Contracts\FolderRepositoryInterface;
use App\Domain\FileHosting\Repositories\Eloquent\FolderRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class FileHostingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FolderRepositoryInterface::class, FolderRepository::class);
    }

    public function boot(): void
    {
        Gate::policy(Folder::class, FolderPolicy::class);
        Gate::policy(HostedFile::class, HostedFilePolicy::class);
    }
}
