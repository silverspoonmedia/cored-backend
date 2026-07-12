<?php

namespace App\Providers\Domain;

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
use App\Domain\Playground\Models\Order;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class PaymentGatewayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InvoiceRepositoryInterface::class, InvoiceRepository::class);
        $this->app->bind(PaymentGatewayRepositoryInterface::class, PaymentGatewayRepository::class);
        $this->app->bind(SubscriptionRepositoryInterface::class, SubscriptionRepository::class);
        $this->app->bind(InvoiceAttachableServiceInterface::class, InvoiceAttachableService::class);
    }

    public function boot(): void
    {
        Relation::morphMap([
            'order' => Order::class,
            'subscription' => Subscription::class,
        ]);

        $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('payments:midtrans-reconcile-pending')->everyFiveMinutes();
            $schedule->command('payments:reconcile-withdrawable-funds')->everyTenMinutes();
        });
    }
}
