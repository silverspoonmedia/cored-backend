<?php

namespace App\Providers\Domain;

use App\Domain\Archivd\Models\Folder;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Policies\FolderPolicy;
use App\Domain\Archivd\Policies\HostedFilePolicy;
use App\Domain\Archivd\Repositories\Contracts\FolderRepositoryInterface;
use App\Domain\Archivd\Repositories\Eloquent\FolderRepository;
use App\Domain\Archivd\Services\QuotaService;
use App\Domain\Archivd\Support\ArchivdEnvironmentCatalog;
use App\Domain\Archivd\Support\Billing\ArchivdFulfillmentHandler;
use App\Domain\Archivd\Support\Billing\ArchivdServiceStates;
use App\Domain\Archivd\Support\Billing\ArchivdTierObserver;
use App\Domain\Archivd\Support\Navigation\ArchivdMenuContributor;
use App\Domain\Archivd\Support\Settings\ArchivdSettingContributor;
use App\Support\Billing\FulfillmentRegistry;
use App\Support\Navigation\MenuContributorRegistry;
use App\Support\Settings\SettingCatalog;
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

        // Kernel seam: the settings catalog owns the contract, this product owns
        // the keys (see ArchivdSettingContributor).
        if ($this->app->bound(SettingCatalog::class)) {
            $this->app->make(SettingCatalog::class)
                ->register(ArchivdSettingContributor::class);
        }
    }

    public function boot(): void
    {
        Gate::policy(Folder::class, FolderPolicy::class);
        Gate::policy(HostedFile::class, HostedFilePolicy::class);

        // Archivd's Service factory states — SKU codes and plan sizes are product
        // knowledge, so they are registered here rather than in the billing package.
        ArchivdServiceStates::register();

        // Kernel seam: the navigation package owns the tree, Archivd owns the
        // nodes its files and settings pages add to it.
        if ($this->app->bound(MenuContributorRegistry::class)) {
            $this->app->make(MenuContributorRegistry::class)
                ->register(ArchivdMenuContributor::class);
        }

        // Kernel seam: the direct-upload engine ships in the kernel and reads
        // `storage.*`; Archivd's own Worker proxy settings are published into it
        // here, so removing the product leaves the kernel defaults in place.
        config([
            'storage.upload_proxy' => config('archivd.upload_proxy', [
                'base_url' => '',
                'token' => '',
                'disk_targets' => [],
            ]),
            'storage.debug_log_requests' => (bool) config('archivd.debug_log_requests', false),
            // Kernel seam: the typed env catalog validates `.env.example`, and the
            // storage keys it declares are this product's.
            'env-catalog.types' => [
                ...(array) config('env-catalog.types', []),
                ...ArchivdEnvironmentCatalog::types(),
            ],
        ]);
    }
}
