<?php

namespace App\Domain\PaymentGateway\Contracts;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\Service\Models\ServiceAddonRenewal;
use App\Domain\Service\Models\ServicePurchase;

interface InvoiceAttachableServiceInterface
{
    public function createPendingInvoiceForServicePurchase(
        ServicePurchase $purchase,
        ?string $paymentGatewayId,
    ): Invoice;

    public function createPendingInvoiceForServiceAddonRenewal(
        ServiceAddonRenewal $renewal,
        ?string $paymentGatewayId,
    ): Invoice;
}
