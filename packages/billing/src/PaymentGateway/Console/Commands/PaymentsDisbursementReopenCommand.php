<?php

namespace App\Domain\PaymentGateway\Console\Commands;

use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Domain\PaymentGateway\Services\Billing\InvoiceDisbursementService;
use Illuminate\Console\Command;

class PaymentsDisbursementReopenCommand extends Command
{
    protected $signature = 'payments:disbursement-reopen {invoice : Invoice UUID}';

    protected $description = 'After a failed payout, move invoice back to eligible (or none if no longer withdrawable).';

    public function handle(InvoiceRepositoryInterface $invoices, InvoiceDisbursementService $disbursements): int
    {
        $invoice = $invoices->findOrFailById((string) $this->argument('invoice'));

        try {
            $updated = $disbursements->resetFailedToEligible($invoice);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Disbursement state reset for '.$updated->invoice_number.' → '.$updated->disbursement_status->value);

        return self::SUCCESS;
    }
}
