<?php

namespace App\Domain\PaymentGateway\Support;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\Subscription;
use App\Domain\Service\Models\ServiceAddonRenewal;
use App\Domain\Service\Models\ServicePurchase;

/**
 * Decide whether a user may subscribe to private-payments.invoice.{id}.
 *
 * Mirrors ServicePurchasePolicy: staff with view invoice, or invoiceable owner.
 */
class InvoiceChannelAuthorizer
{
    public function canSubscribe(User $user, Invoice $invoice): bool
    {
        if ($user->can('view invoice')) {
            return true;
        }

        $invoice->loadMissing('invoiceable');
        $invoiceable = $invoice->invoiceable;

        if ($invoiceable instanceof ServicePurchase) {
            return (string) $invoiceable->user_id === (string) $user->id;
        }

        if ($invoiceable instanceof ServiceAddonRenewal) {
            return (string) $invoiceable->user_id === (string) $user->id;
        }

        if ($invoiceable instanceof Subscription) {
            // Demo morph has no user_id — match authenticated email to subscriber_email.
            $email = strtolower(trim((string) ($invoiceable->subscriber_email ?? '')));

            return $email !== '' && $email === strtolower(trim((string) $user->email));
        }

        return false;
    }
}
