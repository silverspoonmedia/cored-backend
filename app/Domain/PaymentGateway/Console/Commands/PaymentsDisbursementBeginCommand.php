<?php

namespace App\Domain\PaymentGateway\Console\Commands;

use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Domain\PaymentGateway\Services\Billing\InvoiceDisbursementService;
use Illuminate\Console\Command;

class PaymentsDisbursementBeginCommand extends Command
{
    protected $signature = 'payments:disbursement-begin {invoice : Invoice UUID}';

    protected $description = 'Move an invoice from eligible → processing before you send money (prevents parallel runs).';

    public function handle(InvoiceRepositoryInterface $invoices, InvoiceDisbursementService $disbursements): int
    {
        $invoice = $invoices->findOrFailById((string) $this->argument('invoice'));

        try {
            $updated = $disbursements->markProcessing($invoice);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Disbursement started for '.$updated->invoice_number.' (processing).');

        return self::SUCCESS;
    }
}
