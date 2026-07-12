<?php

namespace App\Domain\PaymentGateway\Contracts;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\Playground\Models\Order;

interface InvoiceAttachableServiceInterface
{
    public function createPendingInvoiceForOrder(Order $order, ?string $paymentGatewayId): Invoice;

    public function syncOrderInvoiceAmountIfPending(Order $order): void;

    public function deletePendingInvoiceForOrder(Order $order): void;
}
