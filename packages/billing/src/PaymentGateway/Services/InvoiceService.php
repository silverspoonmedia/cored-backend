<?php

namespace App\Domain\PaymentGateway\Services;

use App\Domain\PaymentGateway\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;

class InvoiceService
{
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
