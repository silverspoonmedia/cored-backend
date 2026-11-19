<?php

namespace App\Domain\PaymentGateway\Console\Commands;

// Contract
use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
// Service
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransInvoiceSynchronizer;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransSdk;
use App\Support\Encryption\DecryptSourceContext;
// External
use Illuminate\Console\Command;
// Internal
use Midtrans\Transaction;

// Class
class PaymentsMidtransReconcilePendingInvoicesCommand extends Command
{
    protected $signature = 'payments:midtrans-reconcile-pending {--limit=50 : Max invoices per run}';

    protected $description = 'Poll Midtrans Get Status API for pending/challenge invoices (cron backup when webhooks fail).';

    public function handle(
        InvoiceRepositoryInterface $invoices,
        MidtransInvoiceSynchronizer $synchronizer
    ): int {
        $gateway = MidtransSdk::resolveGateway();
        MidtransSdk::configure($gateway);

        try {
            MidtransSdk::assertConfigured($gateway);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));

        $invoiceRows = $invoices->findForGatewayWithStatuses(
            'midtrans',
            ['pending', 'challenge'],
            $limit,
            'updated_at',
            'asc'
        );

        $ok = 0;
        $fail = 0;

        DecryptSourceContext::run('cron.midtrans_reconcile_pending', function () use ($invoiceRows, $synchronizer, &$ok, &$fail): void {
            foreach ($invoiceRows as $invoice) {
                try {
                    $status = Transaction::status($invoice->invoice_number);
                    $synchronizer->sync($invoice, $status);
                    $ok++;
                } catch (\Throwable $e) {
                    $fail++;
                    $this->warn($invoice->invoice_number.': '.$e->getMessage());
                }
            }
        });

        $this->info("Reconciled {$ok} invoice(s); {$fail} failed.");

        return self::SUCCESS;
    }
}
