<?php

namespace App\Domain\PaymentGateway\Contracts;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\Playground\Models\Order;
use App\Domain\Service\Models\ServicePurchase;

interface InvoiceAttachableServiceInterface
{
    public function createPendingInvoiceForOrder(Order $order, ?string $paymentGatewayId): Invoice;

    public function syncOrderInvoiceAmountIfPending(Order $order): void;

    public function deletePendingInvoiceForOrder(Order $order): void;

    public function createPendingInvoiceForServicePurchase(
        ServicePurchase $purchase,
        ?string $paymentGatewayId,
    ): Invoice;
}
