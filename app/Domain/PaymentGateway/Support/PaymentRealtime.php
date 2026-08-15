<?php

namespace App\Domain\PaymentGateway\Support;

use App\Domain\PaymentGateway\Events\InvoiceListChanged;
use App\Domain\PaymentGateway\Events\InvoiceStatusChanged;
use App\Domain\PaymentGateway\Events\PaymentRoutingCatalogChanged;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\Subscription;
use App\Domain\Service\Models\ServiceAddonRenewal;
use App\Domain\Service\Models\ServicePurchase;
use App\Support\Broadcasting\BroadcastConnection;
use Illuminate\Support\Facades\Log;

/**
 * Fire Payment Gateway realtime events after invoice status transitions.
 *
 * Gated on {@see BroadcastConnection::isConfigured()} only — independent of
 * `platform.notifications.delivery` so checkout can stay live while the bell polls.
 */
class PaymentRealtime
{
    public function enabled(): bool
    {
        return BroadcastConnection::isConfigured();
    }

    /**
     * Client-safe Echo options for Blade checkout pages (null when unconfigured).
     *
     * @return array<string, mixed>|null
     */
    public function clientConfig(): ?array
    {
        return BroadcastConnection::socketClientConfig();
    }

    /**
     * Broadcast when invoice status actually changed. No-op when broadcast unconfigured
     * or previous === current status.
     */
    public function invoiceStatusChanged(Invoice $invoice, string $previousStatus): void
    {
        $current = (string) $invoice->status;

        if ($previousStatus === $current) {
            return;
        }

        if (! $this->enabled()) {
            return;
        }

        $invoice->loadMissing('invoiceable');

        try {
            event(new InvoiceStatusChanged(
                invoiceId: (string) $invoice->id,
                status: $current,
                previousStatus: $previousStatus,
                paidAt: $invoice->paid_at?->toIso8601String(),
                expiredAt: $invoice->expired_at?->toIso8601String(),
                invoiceableType: $invoice->invoiceable_type !== null
                    ? (string) $invoice->invoiceable_type
                    : null,
                invoiceableId: $invoice->invoiceable_id !== null
                    ? (string) $invoice->invoiceable_id
                    : null,
                invoiceableStatus: $this->resolveInvoiceableStatus($invoice),
            ));

            $this->invoiceListChanged($current);
        } catch (\Throwable $e) {
            // Never break payment fulfillment because of a push failure.
            Log::warning('PaymentRealtime broadcast failed', [
                'invoice_id' => $invoice->id,
                'status' => $current,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast a lightweight signal to the admin invoice list when any
     * invoice status transitions. Count-only — no invoice payload.
     */
    public function invoiceListChanged(string $action): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            event(new InvoiceListChanged($action));
        } catch (\Throwable $e) {
            Log::warning('PaymentRealtime invoice list broadcast failed', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast when payment channel/route catalog changes (admin toggle or policy sync).
     */
    public function routingCatalogChanged(string $reason, ?string $channelCode = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            event(new PaymentRoutingCatalogChanged(
                revision: time(),
                reason: $reason,
                channelCode: $channelCode,
            ));
        } catch (\Throwable $e) {
            Log::warning('PaymentRealtime routing catalog broadcast failed', [
                'reason' => $reason,
                'channel_code' => $channelCode,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveInvoiceableStatus(Invoice $invoice): ?string
    {
        $invoiceable = $invoice->invoiceable;

        if ($invoiceable instanceof ServicePurchase
            || $invoiceable instanceof ServiceAddonRenewal
            || $invoiceable instanceof Subscription) {
            return $this->statusToString($invoiceable->status ?? null);
        }

        return null;
    }

    private function statusToString(mixed $status): ?string
    {
        if ($status === null) {
            return null;
        }

        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }

        if (is_object($status) && property_exists($status, 'value')) {
            return (string) $status->value;
        }

        return (string) $status;
    }
}
