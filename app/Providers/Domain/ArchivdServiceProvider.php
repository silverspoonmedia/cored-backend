<?php

namespace App\Providers\Domain;

use App\Domain\Archivd\Models\Folder;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Policies\FolderPolicy;
use App\Domain\Archivd\Policies\HostedFilePolicy;
use App\Domain\Archivd\Repositories\Contracts\FolderRepositoryInterface;
use App\Domain\Archivd\Repositories\Eloquent\FolderRepository;
use App\Domain\Archivd\Services\QuotaService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ArchivdServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FolderRepositoryInterface::class, FolderRepository::class);
        $this->app->scoped(QuotaService::class);
    }

    public function boot(): void
    {
        Gate::policy(Folder::class, FolderPolicy::class);
        Gate::policy(HostedFile::class, HostedFilePolicy::class);
    }
}
