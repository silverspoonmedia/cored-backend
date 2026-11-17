<?php

namespace App\Domain\PaymentGateway\Console\Commands;

use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Domain\PaymentGateway\Services\Billing\InvoiceDisbursementService;
use Illuminate\Console\Command;

class PaymentsDisbursementFailCommand extends Command
{
    protected $signature = 'payments:disbursement-fail {invoice : Invoice UUID} {--note= : Optional note stored in disbursement_reference}';

    protected $description = 'Mark a processing disbursement as failed (e.g. bank rejected the transfer).';

    public function handle(InvoiceRepositoryInterface $invoices, InvoiceDisbursementService $disbursements): int
    {
        $invoice = $invoices->findOrFailById((string) $this->argument('invoice'));
        $note = $this->option('note');

        try {
            $updated = $disbursements->markFailed($invoice, is_string($note) ? $note : null);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Disbursement marked failed for '.$updated->invoice_number.'.');

        return self::SUCCESS;
    }
}
