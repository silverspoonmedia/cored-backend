<?php

namespace App\Domain\PaymentGateway;

use App\Domain\PaymentGateway\Console\Commands\PaymentsAuditInvoiceFundsCommand;
use App\Domain\PaymentGateway\Console\Commands\PaymentsDisbursementBeginCommand;
use App\Domain\PaymentGateway\Console\Commands\PaymentsDisbursementCompleteCommand;
use App\Domain\PaymentGateway\Console\Commands\PaymentsDisbursementFailCommand;
use App\Domain\PaymentGateway\Console\Commands\PaymentsDisbursementReopenCommand;
use App\Domain\PaymentGateway\Console\Commands\PaymentsMidtransReconcilePendingInvoicesCommand;
use App\Domain\PaymentGateway\Console\Commands\PaymentsReconcileWithdrawableFundsCommand;
use App\Domain\PaymentGateway\Console\Commands\PaymentsSyncRoutingPolicyCommand;
use App\Domain\PaymentGateway\Console\Commands\PaymentsWebhookUrlsCommand;
use App\Domain\PaymentGateway\Contracts\InvoiceAttachableServiceInterface;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\Subscription;
use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Domain\PaymentGateway\Repositories\Contracts\PaymentGatewayRepositoryInterface;
use App\Domain\PaymentGateway\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Domain\PaymentGateway\Repositories\Eloquent\InvoiceRepository;
use App\Domain\PaymentGateway\Repositories\Eloquent\PaymentGatewayRepository;
use App\Domain\PaymentGateway\Repositories\Eloquent\SubscriptionRepository;
use App\Domain\PaymentGateway\Services\InvoiceAttachableService;
use App\Domain\PaymentGateway\Support\Broadcasting\PaymentGatewayChannelRegistrar;
use App\Domain\PaymentGateway\Support\Encryption\InvoicePayloadColumnMigrator;
use App\Domain\Service\Console\Commands\ProcessServiceAddonRenewalsCommand;
use App\Domain\Service\Console\Commands\ProcessServiceEntitlementsCommand;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceAddonRenewal;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Policies\ServicePolicy;
use App\Domain\Service\Policies\ServicePurchasePolicy;
use App\Domain\Service\Services\ServiceEntitlementService;
use App\Support\Broadcasting\ChannelRegistrarRegistry;
use App\Support\Encryption\LegacyColumnMigratorRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * One provider for `silverspoonmedia/billing` — three core domains, one package.
 *
 * `Currency` owns the money vocabulary (catalog + FX snapshots), `Service` owns
 * the catalog and the entitlement ledger, `PaymentGateway` owns the invoice that
 * collects for it; they are one money surface and are consumed together, so they
 * ship as one Composer package while staying three namespaces, three route files
 * and three registry entries.
 */
class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/payment.php', 'payment');
        $this->mergeConfigFrom(__DIR__.'/../../config/payment-routing.php', 'payment-routing');
        $this->mergeConfigFrom(__DIR__.'/../../config/service.php', 'service');

        $this->app->bind(InvoiceRepositoryInterface::class, InvoiceRepository::class);
        $this->app->bind(PaymentGatewayRepositoryInterface::class, PaymentGatewayRepository::class);
        $this->app->bind(SubscriptionRepositoryInterface::class, SubscriptionRepository::class);
        $this->app->bind(InvoiceAttachableServiceInterface::class, InvoiceAttachableService::class);

        $this->app->scoped(ServiceEntitlementService::class);
    }

    public function boot(): void
    {
        // Package channels and P0 column migrators register through the kernel
        // registries, so `routes/channels.php` and `encryption:migrate-p0` never
        // import a package model. Boot, not register: this provider is
        // auto-discovered and therefore boots *before* the host binds the kernel
        // singletons, so `bound()` in register() would read false and the
        // package's channels would silently never authorize.
        if ($this->app->bound(ChannelRegistrarRegistry::class)) {
            $this->app->make(ChannelRegistrarRegistry::class)->register(PaymentGatewayChannelRegistrar::class);
        }

        if ($this->app->bound(LegacyColumnMigratorRegistry::class)) {
            $this->app->make(LegacyColumnMigratorRegistry::class)->register(InvoicePayloadColumnMigrator::class);
        }

        Relation::morphMap([
            'subscription' => Subscription::class,
            'service_purchase' => ServicePurchase::class,
            'service_addon_renewal' => ServiceAddonRenewal::class,
        ]);

        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(ServicePurchase::class, ServicePurchasePolicy::class);

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Plain (non-namespaced) lang path: FileLoader::loadPaths() resolves
        // "{path}/{locale}/{group}.php", so the path is the parent of the locale
        // directories. Keys stay `api/payments.*`, `api/services.*`, `api/currencies.*`;
        // the currency pack belongs to the Currency domain in this same package.
        $this->app['translator']->getLoader()->addPath(__DIR__.'/../../resources/lang');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PaymentsAuditInvoiceFundsCommand::class,
                PaymentsDisbursementBeginCommand::class,
                PaymentsDisbursementCompleteCommand::class,
                PaymentsDisbursementFailCommand::class,
                PaymentsDisbursementReopenCommand::class,
                PaymentsMidtransReconcilePendingInvoicesCommand::class,
                PaymentsReconcileWithdrawableFundsCommand::class,
                PaymentsSyncRoutingPolicyCommand::class,
                PaymentsWebhookUrlsCommand::class,
                ProcessServiceAddonRenewalsCommand::class,
                ProcessServiceEntitlementsCommand::class,
            ]);
        }
    }
}
