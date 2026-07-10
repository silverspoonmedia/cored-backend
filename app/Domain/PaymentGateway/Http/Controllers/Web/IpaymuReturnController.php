<?php

namespace App\Domain\PaymentGateway\Http\Controllers\Web;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\Subscription;
use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuPaymentService;
use App\Domain\Playground\Models\Order;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Browser return/cancel landing after iPaymu hosted checkout.
 *
 * iPaymu redirects the payer here (ureturn / ucancel). This is UX-only; invoice
 * status is primarily updated by the server-to-server webhook (unotify).
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

        if ($invoice === null) {
            return redirect()
                ->route('invoices.index')
                ->with('warning', __('web/payments.ipaymu_return.invoice_not_found'));
        }

        if (! $cancelled) {
            $this->attemptSync($invoice);
            $invoice = $invoice->fresh() ?? $invoice;
        }

        return $this->redirectForInvoice($invoice, $request, $cancelled);
    }

    private function attemptSync(Invoice $invoice): void
    {
        if ($invoice->external_transaction_id === null || $invoice->external_transaction_id === '') {
            return;
        }

        $this->payments->syncInvoiceStatus($invoice);
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

    private function redirectForInvoice(Invoice $invoice, Request $request, bool $cancelled): RedirectResponse
    {
        $invoice->loadMissing('invoiceable');

        $flashKey = $cancelled ? 'warning' : $this->flashKeyForStatus((string) $invoice->status);
        $flashMessage = $cancelled
            ? __('web/payments.ipaymu_return.cancelled')
            : $this->messageForStatus((string) $invoice->status);

        if ($request->query('redirect') === 'order' && is_string($request->query('order')) && Str::isUuid($request->query('order'))) {
            return redirect()
                ->route('orders.show', $request->query('order'))
                ->with($flashKey, $flashMessage);
        }

        if ($request->query('redirect') === 'subscription'
            && is_string($request->query('subscription'))
            && Str::isUuid($request->query('subscription'))) {
            return redirect()
                ->route('subscriptions.show', $request->query('subscription'))
                ->with($flashKey, $flashMessage);
        }

        if ($invoice->invoiceable instanceof Order) {
            return redirect()
                ->route('orders.show', $invoice->invoiceable_id)
                ->with($flashKey, $flashMessage);
        }

        if ($invoice->invoiceable instanceof Subscription) {
            return redirect()
                ->route('subscriptions.show', $invoice->invoiceable_id)
                ->with($flashKey, $flashMessage);
        }

        return redirect()
            ->route('invoices.show', $invoice->id)
            ->with($flashKey, $flashMessage);
    }

    private function flashKeyForStatus(string $status): string
    {
        return match ($status) {
            'paid', 'partially_refunded' => 'success',
            'failed', 'cancelled', 'expired' => 'error',
            default => 'info',
        };
    }

    private function messageForStatus(string $status): string
    {
        return match ($status) {
            'paid', 'partially_refunded' => __('web/payments.ipaymu_return.paid'),
            'failed' => __('web/payments.ipaymu_return.failed'),
            'cancelled' => __('web/payments.ipaymu_return.cancelled'),
            'expired' => __('web/payments.ipaymu_return.expired'),
            default => __('web/payments.ipaymu_return.pending'),
        };
    }
}
