<?php

namespace App\Domain\PaymentGateway\Support\Broadcasting;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Support\InvoiceChannelAuthorizer;
use App\Domain\Service\Models\ServicePurchase;
use App\Support\Broadcasting\Contracts\ChannelRegistrar;
use Illuminate\Support\Facades\Broadcast;
use Throwable;

/**
 * Broadcast authorization owned by the billing package.
 *
 * `routes/channels.php` stays the single file the framework reads, but the
 * channels below belong to the invoice/purchase models this package owns — the
 * host file declares none of them and imports none of these models.
 */
final class PaymentGatewayChannelRegistrar implements ChannelRegistrar
{
    public function register(): void
    {
        Broadcast::channel('payments.invoice.{invoiceId}', function ($user, string $invoiceId): bool {
            try {
                $invoice = Invoice::query()->find($invoiceId);

                if ($invoice === null) {
                    return false;
                }

                return app(InvoiceChannelAuthorizer::class)->canSubscribe($user, $invoice);
            } catch (Throwable) {
                return false;
            }
        });

        Broadcast::channel('payments.routing', fn ($user): bool => $user->can('initiate payment') || $user->can('view invoice'));

        Broadcast::channel('payments.admin', fn ($user): bool => $user->can('view invoice'));

        Broadcast::channel('services.catalog', fn ($user): bool => $user->can('view service'));

        Broadcast::channel('services.purchase.{purchaseId}', fn ($user, string $purchaseId): bool => ServicePurchase::query()
            ->whereKey($purchaseId)
            ->where('user_id', $user->id)
            ->exists());
    }
}
