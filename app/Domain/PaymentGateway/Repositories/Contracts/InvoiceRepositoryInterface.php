<?php

namespace App\Domain\PaymentGateway\Repositories\Contracts;

use App\Domain\PaymentGateway\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;

interface InvoiceRepositoryInterface
{
    public function findOrFailById(string $id): Invoice;

    public function findByInvoiceNumberForGateway(string $invoiceNumber, string $gatewayName): ?Invoice;

    public function findByExternalTransactionIdForGateway(string $externalTransactionId, string $gatewayName): ?Invoice;

    public function save(Invoice $invoice): void;

    /**
     * Invoices for a gateway filtered by status (e.g. cron reconcile). {@see $orderBy} is allow-listed.
     *
     * @param  list<string>  $statuses
     * @return Collection<int, Invoice>
     */
    public function findForGatewayWithStatuses(
        string $gatewayName,
        array $statuses,
        int $limit,
        string $orderBy = 'updated_at',
        string $direction = 'asc'
    ): Collection;

    /**
     * Paid invoices where gateway funds may not yet be withdrawable (e.g. Midtrans capture, iPaymu unsettle).
     *
     * @return Collection<int, Invoice>
     */
    public function findPaidAwaitingWithdrawable(
        int $limit,
        string $orderBy = 'updated_at',
        string $direction = 'asc'
    ): Collection;
}
