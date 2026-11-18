<?php

namespace App\Domain\PaymentGateway\Console\Commands;

use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Domain\PaymentGateway\Services\Billing\InvoiceDisbursementService;
use Illuminate\Console\Command;

class PaymentsDisbursementCompleteCommand extends Command
{
    protected $signature = 'payments:disbursement-complete {invoice : Invoice UUID} {--reference= : Bank / internal payout reference (required)}';

    protected $description = 'Mark disbursement completed (processing → completed) with an idempotency reference.';

    public function handle(InvoiceRepositoryInterface $invoices, InvoiceDisbursementService $disbursements): int
    {
        $reference = (string) $this->option('reference');

        if (trim($reference) === '') {
            $this->error('Pass --reference=... (your payout id / bank ref) so you cannot “complete” twice without traceability.');

            return self::FAILURE;
        }

        $invoice = $invoices->findOrFailById((string) $this->argument('invoice'));

        try {
            $updated = $disbursements->markCompleted($invoice, $reference);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Disbursement completed for '.$updated->invoice_number.' ref='.$updated->disbursement_reference);

        return self::SUCCESS;
    }
}
