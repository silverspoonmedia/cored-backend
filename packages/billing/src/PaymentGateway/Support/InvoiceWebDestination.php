<?php

namespace App\Domain\PaymentGateway\Support;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Support\SpaPublicUrl;

final class InvoiceWebDestination
{
    /**
     * @param  Invoice|array<string, mixed>  $invoice
     */
    public static function showUrl(Invoice|array $invoice): string
    {
        $type = self::invoiceableType($invoice);
        $invoiceableId = self::invoiceableId($invoice);
        $invoiceId = self::invoiceId($invoice);

        if ($type === 'service_purchase' && $invoiceableId !== null) {
            return SpaPublicUrl::to('/apps/service-purchases/'.$invoiceableId);
        }

        return SpaPublicUrl::to('/apps/invoices/'.$invoiceId);
    }

    /**
     * @param  Invoice|array<string, mixed>  $invoice
     */
    public static function shouldRedirectToPurchaseHub(Invoice|array $invoice): bool
    {
        return self::invoiceableType($invoice) === 'service_purchase'
            && self::invoiceableId($invoice) !== null;
    }

    /**
     * @param  Invoice|array<string, mixed>  $invoice
     */
    public static function purchaseHubUrl(Invoice|array $invoice): ?string
    {
        if (! self::shouldRedirectToPurchaseHub($invoice)) {
            return null;
        }

        return SpaPublicUrl::to('/apps/service-purchases/'.self::invoiceableId($invoice));
    }

    /**
     * @param  Invoice|array<string, mixed>  $invoice
     */
    private static function invoiceableType(Invoice|array $invoice): ?string
    {
        return $invoice instanceof Invoice
            ? (string) $invoice->invoiceable_type
            : (data_get($invoice, 'invoiceable_type') !== null ? (string) data_get($invoice, 'invoiceable_type') : null);
    }

    /**
     * @param  Invoice|array<string, mixed>  $invoice
     */
    private static function invoiceableId(Invoice|array $invoice): ?string
    {
        $id = $invoice instanceof Invoice ? $invoice->invoiceable_id : data_get($invoice, 'invoiceable_id');

        return $id !== null ? (string) $id : null;
    }

    /**
     * @param  Invoice|array<string, mixed>  $invoice
     */
    private static function invoiceId(Invoice|array $invoice): string
    {
        $id = $invoice instanceof Invoice ? $invoice->id : data_get($invoice, 'id');

        return (string) $id;
    }
}
