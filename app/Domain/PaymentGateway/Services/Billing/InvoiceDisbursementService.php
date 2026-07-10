<?php

namespace App\Domain\PaymentGateway\Services\Billing;

use App\Domain\PaymentGateway\Enums\DisbursementStatus;
use App\Domain\PaymentGateway\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent-ish disbursement transitions (your bank / payout provider should still verify).
 */
class InvoiceDisbursementService
{
    public function markProcessing(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->disbursement_status !== DisbursementStatus::Eligible) {
                throw new \RuntimeException('Invoice is not eligible for disbursement (expected eligible, got '.$locked->disbursement_status->value.').');
            }

            if ($locked->withdrawable_at === null) {
                throw new \RuntimeException('Invoice has no withdrawable_at; reconcile gateway state first.');
            }

            $locked->disbursement_status = DisbursementStatus::Processing;
            $locked->disbursement_processing_at = now();
            $locked->save();

            return $locked->fresh();
        });
    }

    public function markCompleted(Invoice $invoice, string $reference): Invoice
    {
        $reference = trim($reference);

        if ($reference === '') {
            throw new \RuntimeException('disbursement_reference is required to complete disbursement.');
        }

        return DB::transaction(function () use ($invoice, $reference) {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->disbursement_status !== DisbursementStatus::Processing) {
                throw new \RuntimeException('Invoice is not in processing disbursement state (got '.$locked->disbursement_status->value.').');
            }

            $locked->disbursement_status = DisbursementStatus::Completed;
            $locked->disbursed_at = now();
            $locked->disbursement_reference = $reference;
            $locked->save();

            return $locked->fresh();
        });
    }

    public function markFailed(Invoice $invoice, ?string $note = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $note) {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->disbursement_status !== DisbursementStatus::Processing) {
                throw new \RuntimeException('Only processing invoices can be marked failed (got '.$locked->disbursement_status->value.').');
            }

            $locked->disbursement_status = DisbursementStatus::Failed;
            $locked->disbursement_processing_at = null;
            if ($note !== null && $note !== '') {
                $locked->disbursement_reference = $note;
            }
            $locked->save();

            return $locked->fresh();
        });
    }

    /**
     * After a payout failure: back to eligible if funds are still withdrawable.
     */
    public function resetFailedToEligible(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->disbursement_status !== DisbursementStatus::Failed) {
                throw new \RuntimeException('Invoice is not in failed disbursement state.');
            }

            if ($locked->withdrawable_at === null) {
                $locked->disbursement_status = DisbursementStatus::None;
            } else {
                $locked->disbursement_status = DisbursementStatus::Eligible;
            }

            $locked->disbursement_reference = null;
            $locked->save();

            return $locked->fresh();
        });
    }
}
