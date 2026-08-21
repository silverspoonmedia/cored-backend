<?php

namespace App\Domain\PaymentGateway\Http\Controllers\Web;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\Subscription;
use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuPaymentService;
use App\Domain\Service\Models\ServicePurchase;
use App\Http\Controllers\Controller;
use App\Support\Encryption\DecryptSourceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Browser return/cancel landing after iPaymu hosted checkout.
 *
 * iPaymu redirects the payer here (ureturn / ucancel). This is UX-only; invoice
 * status is primarily updated by the server-to-server webhook (unotify). The
 * Blade app is gone, so after resolving/syncing the invoice we hand the browser
 * to the SPA `/payment/return|cancel` page, which owns status display and the
 * onward redirect (invoice / subscription / service-purchase).
 */
class IpaymuReturnController extends Controller
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private IpaymuPaymentService $payments,
    ) {}

    public function return(Request $request): RedirectResponse
    {
        return $this->handle($request, cancelled: false);
    }

    public function cancel(Request $request): RedirectResponse
    {
        return $this->handle($request, cancelled: true);
    }

    private function handle(Request $request, bool $cancelled): RedirectResponse
    {
        $invoice = $this->resolveInvoice($request);

        if ($invoice !== null && ! $cancelled) {
            $this->attemptSync($invoice);
            $invoice = $invoice->fresh() ?? $invoice;
        }

        return redirect()->away($this->spaReturnUrl($request, $invoice, $cancelled));
    }

    private function attemptSync(Invoice $invoice): void
    {
        if ($invoice->external_transaction_id === null || $invoice->external_transaction_id === '') {
            return;
        }

        DecryptSourceContext::run('payment.ipaymu.return_url', function () use ($invoice): void {
            $this->payments->syncInvoiceStatus($invoice);
        });
    }

    private function spaReturnUrl(Request $request, ?Invoice $invoice, bool $cancelled): string
    {
        $base = rtrim((string) config('services.spa.url'), '/');
        $path = $cancelled ? '/payment/cancel' : '/payment/return';

        $query = [];

        if ($invoice !== null) {
            $invoice->loadMissing('invoiceable');
            $query['invoice_id'] = $invoice->id;

            if ($invoice->invoiceable instanceof ServicePurchase) {
                $query['type'] = 'service_purchase';
                $query['reference_id'] = $invoice->invoiceable_id;
            } elseif ($invoice->invoiceable instanceof Subscription) {
                $query['type'] = 'subscription';
                $query['reference_id'] = $invoice->invoiceable_id;
            }
        }

        // Preserve explicit hints from the checkout return query when present.
        if (! isset($query['type']) && is_string($request->query('redirect'))) {
            $hint = $request->query('redirect');
            if ($hint === 'service_purchase' || $hint === 'subscription') {
                $query['type'] = $hint;
                $idKey = $hint === 'service_purchase' ? 'purchase' : 'subscription';
                $id = $request->query($idKey);
                if (is_string($id) && Str::isUuid($id)) {
                    $query['reference_id'] = $id;
                }
            }
        }

        $trxId = $request->query('trx_id');
        if (is_numeric($trxId)) {
            $query['trx_id'] = (string) $trxId;
        }

        if (! isset($query['reference_id'])) {
            foreach (['reference_id', 'referenceId', 'ReferenceId'] as $key) {
                $referenceId = $request->query($key);
                if (is_string($referenceId) && $referenceId !== '') {
                    $query['reference_id'] = $referenceId;
                    break;
                }
            }
        }

        $qs = $query === [] ? '' : '?'.http_build_query($query);

        return $base.$path.$qs;
    }

    private function resolveInvoice(Request $request): ?Invoice
    {
        $invoiceId = $request->query('invoice_id');

        if (is_string($invoiceId) && Str::isUuid($invoiceId)) {
            $invoice = Invoice::query()->find($invoiceId);

            if ($invoice !== null) {
                return $invoice;
            }
        }

        $trxId = $request->query('trx_id');

        if (is_numeric($trxId)) {
            $invoice = $this->invoices->findByExternalTransactionIdForGateway((string) $trxId, 'ipaymu');

            if ($invoice !== null) {
                return $invoice;
            }
        }

        foreach (['reference_id', 'referenceId', 'ReferenceId'] as $key) {
            $referenceId = $request->query($key);

            if (! is_string($referenceId) || $referenceId === '') {
                continue;
            }

            $invoice = $this->invoices->findByInvoiceNumberForGateway($referenceId, 'ipaymu');

            if ($invoice !== null) {
                return $invoice;
            }
        }

        return null;
    }
}
