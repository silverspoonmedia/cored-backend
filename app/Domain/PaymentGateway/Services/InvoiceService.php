<?php

namespace App\Domain\PaymentGateway\Services;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class InvoiceService
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $repository,
    ) {}

    /**
     * @return Collection<int, Invoice>
     */
    public function list(): Collection
    {
        return Invoice::query()
            ->with(['paymentGateway', 'paymentChannelRouteSnapshot.paymentGateway', 'invoiceable'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function get(string $id): Invoice
    {
        return Invoice::query()
            ->with(['paymentGateway', 'paymentChannelRouteSnapshot.paymentGateway', 'invoiceable'])
            ->findOrFail($id);
    }
}
