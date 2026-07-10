<?php

namespace App\Domain\PaymentGateway\Repositories\Eloquent;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class InvoiceRepository implements InvoiceRepositoryInterface
{
    private const ORDERABLE_COLUMNS = ['updated_at', 'created_at'];

    public function findOrFailById(string $id): Invoice
    {
        return Invoice::query()->findOrFail($id);
    }

    public function findByInvoiceNumberForGateway(string $invoiceNumber, string $gatewayName): ?Invoice
    {
        return Invoice::query()
            ->where('invoice_number', $invoiceNumber)
            ->whereHas('paymentGateway', fn ($q) => $q->where('name', $gatewayName))
            ->first();
    }

    public function findByExternalTransactionIdForGateway(string $externalTransactionId, string $gatewayName): ?Invoice
    {
        return Invoice::query()
            ->where('external_transaction_id', $externalTransactionId)
            ->whereHas('paymentGateway', fn ($q) => $q->where('name', $gatewayName))
            ->first();
    }

    public function save(Invoice $invoice): void
    {
        $invoice->save();
    }

    public function findForGatewayWithStatuses(
        string $gatewayName,
        array $statuses,
        int $limit,
        string $orderBy = 'updated_at',
        string $direction = 'asc'
    ): Collection {
        $column = in_array($orderBy, self::ORDERABLE_COLUMNS, true) ? $orderBy : 'updated_at';
        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $limit = max(1, $limit);

        return Invoice::query()
            ->whereHas('paymentGateway', fn ($q) => $q->where('name', $gatewayName))
            ->whereIn('status', $statuses)
            ->orderBy($column, $dir)
            ->limit($limit)
            ->get();
    }

    public function findPaidAwaitingWithdrawable(
        int $limit,
        string $orderBy = 'updated_at',
        string $direction = 'asc'
    ): Collection {
        $column = in_array($orderBy, self::ORDERABLE_COLUMNS, true) ? $orderBy : 'updated_at';
        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $limit = max(1, $limit);

        return Invoice::query()
            ->with('paymentGateway')
            ->whereIn('status', ['paid', 'partially_refunded'])
            ->whereNull('withdrawable_at')
            ->where(function ($q): void {
                $q->whereHas('paymentGateway', fn ($g) => $g->where('name', 'midtrans'))
                    ->orWhere(function ($q2): void {
                        $q2->whereHas('paymentGateway', fn ($g) => $g->where('name', 'ipaymu'))
                            ->whereNotNull('external_transaction_id');
                    });
            })
            ->orderBy($column, $dir)
            ->limit($limit)
            ->get();
    }
}
