<?php

namespace App\Providers\Domain;

use App\Domain\Microstock\Contracts\AiMetadataService;
use App\Domain\Microstock\Contracts\MarketplaceTransport;
use App\Domain\Microstock\Integrations\SftpMarketplaceTransport;
use App\Domain\Microstock\Services\HttpAiMetadataService;
use App\Domain\Microstock\Support\Ai\MicrostockAdminUsageEventResource;
use App\Domain\Microstock\Support\Ai\MicrostockAiConfig;
use App\Domain\Microstock\Support\Ai\MicrostockAiUsageConsolidator;
use App\Domain\Microstock\Support\Ai\MicrostockAiUsageLedger;
use App\Domain\Microstock\Support\Billing\MicrostockFulfillmentHandler;
use App\Domain\Microstock\Support\Billing\MicrostockServiceStates;
use App\Domain\Microstock\Support\MicrostockEnvironmentCatalog;
use App\Domain\Microstock\Support\Navigation\MicrostockMenuContributor;
use App\Domain\Microstock\Support\Settings\MicrostockSettingContributor;
use App\Support\Ai\AiUsageConsolidatorRegistry;
use App\Support\Ai\Contracts\AiUsageConsolidator;
use App\Support\Ai\Contracts\AiUsageLedger;
use App\Support\Billing\FulfillmentRegistry;
use App\Support\Navigation\MenuContributorRegistry;
use App\Support\Settings\SettingCatalog;
use Illuminate\Support\ServiceProvider;

class MicrostockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiMetadataService::class, HttpAiMetadataService::class);
        $this->app->bind(MarketplaceTransport::class, SftpMarketplaceTransport::class);

        if ($this->app->bound(FulfillmentRegistry::class)) {
            $this->app->make(FulfillmentRegistry::class)
                ->register(MicrostockFulfillmentHandler::class);
        }

        // Kernel seam: the catalog owns the contract, this product owns the keys.
        if ($this->app->bound(SettingCatalog::class)) {
            $this->app->make(SettingCatalog::class)
                ->register(MicrostockSettingContributor::class);
        }
    }

    public function boot(): void
    {
        // Microstock's Service factory states — a SKU code is product knowledge,
        // so it is registered here rather than in the billing package.
        MicrostockServiceStates::register();

        // AI seams: `silverspoonmedia/ai` renders the admin usage dashboard but the
        // ledger — table, relations, consolidation arithmetic — is Microstock's.
        // Boot, not register: auto-discovered package providers boot before the
        // host binds kernel singletons, but this is a host provider so either
        // works; boot keeps the guard cheap and the intent explicit.
        $this->app->bind(AiUsageLedger::class, MicrostockAiUsageLedger::class);
        $this->app->bind(AiUsageConsolidator::class, MicrostockAiUsageConsolidator::class);

        if ($this->app->bound(AiUsageConsolidatorRegistry::class)) {
            $this->app->make(AiUsageConsolidatorRegistry::class)
                ->register((string) config('microstock.ai.inference_provider_slug'), MicrostockAiUsageConsolidator::class);
        }

        // The admin usage dashboard ships in `silverspoonmedia/ai`; the rows come from
        // this product's ledger, so the resource class is published, not imported.
        config(['ai.usage_event_resource' => MicrostockAdminUsageEventResource::class]);

        // Kernel seam: the navigation package owns the tree, Microstock owns the
        // settings node it adds to it.
        if ($this->app->bound(MenuContributorRegistry::class)) {
            $this->app->make(MenuContributorRegistry::class)
                ->register(MicrostockMenuContributor::class);
        }

        // Kernel seam: `silverspoonmedia/ai` names no product. Microstock publishes
        // its two consumers and its ledger table so the package's seeder, the
        // legacy-env importer and the pricing migration stay product-neutral.
        // Boot, not mergeConfigFrom: a config file already loaded must win, and
        // mergeConfigFrom only merges the top level.
        config([
            'ai.consumers' => [
                ...(array) config('ai.consumers', []),
                ...MicrostockAiConfig::consumers(),
            ],
            'ai.ledger_fk_targets' => [
                ...(array) config('ai.ledger_fk_targets', []),
                ...MicrostockAiConfig::ledgerForeignKeys(),
            ],
            // Kernel seam: the typed env catalog validates `.env.example`, and the
            // queue names it declares are this product's.
            'env-catalog.types' => [
                ...(array) config('env-catalog.types', []),
                ...MicrostockEnvironmentCatalog::types(),
            ],
        ]);
    }
}
