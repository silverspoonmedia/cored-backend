<?php

namespace App\Providers\Domain;

use App\Domain\Archivd\Models\Folder;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Policies\FolderPolicy;
use App\Domain\Archivd\Policies\HostedFilePolicy;
use App\Domain\Archivd\Repositories\Contracts\FolderRepositoryInterface;
use App\Domain\Archivd\Repositories\Eloquent\FolderRepository;
use App\Domain\Archivd\Services\QuotaService;
use App\Domain\Archivd\Support\Billing\ArchivdFulfillmentHandler;
use App\Domain\Archivd\Support\Billing\ArchivdServiceStates;
use App\Domain\Archivd\Support\Billing\ArchivdTierObserver;
use App\Support\Billing\FulfillmentRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ArchivdServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FolderRepositoryInterface::class, FolderRepository::class);
        $this->app->scoped(QuotaService::class);

        // Kernel seam: the billing package activates an entitlement and then asks
        // this product what that means for Archivd. Behind `bound()` so a host
        // without the billing package still boots.
        if ($this->app->bound(FulfillmentRegistry::class)) {
            $registry = $this->app->make(FulfillmentRegistry::class);
            $registry->register(ArchivdFulfillmentHandler::class);
            $registry->registerTierObserver(ArchivdTierObserver::class);
        }
    }

    public function boot(): void
    {
        Gate::policy(Folder::class, FolderPolicy::class);
        Gate::policy(HostedFile::class, HostedFilePolicy::class);

        // Archivd's Service factory states — SKU codes and plan sizes are product
        // knowledge, so they are registered here rather than in the billing package.
        ArchivdServiceStates::register();
    }
}
