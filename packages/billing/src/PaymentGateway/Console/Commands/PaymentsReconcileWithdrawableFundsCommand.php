<?php

namespace App\Domain\PaymentGateway\Console\Commands;

use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuPaymentService;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransInvoiceSynchronizer;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransSdk;
use App\Support\Encryption\DecryptSourceContext;
use Illuminate\Console\Command;
use Midtrans\Transaction;

class PaymentsReconcileWithdrawableFundsCommand extends Command
{
    protected $signature = 'payments:reconcile-withdrawable-funds {--limit=50 : Max invoices per run}';

    protected $description = 'Poll Midtrans / iPaymu for paid invoices that are not yet marked withdrawable (settlement / iPaymu SUCCESS).';

    public function handle(
        InvoiceRepositoryInterface $invoices,
        MidtransInvoiceSynchronizer $midtransSynchronizer,
        IpaymuPaymentService $ipaymuPayments
    ): int {
        $limit = max(1, (int) $this->option('limit'));
        $rows = $invoices->findPaidAwaitingWithdrawable($limit);

        if ($rows->isEmpty()) {
            $this->info('No paid invoices awaiting withdrawable state.');

            return self::SUCCESS;
        }

        $needsMidtrans = $rows->contains(fn ($i) => $i->paymentGateway?->name === 'midtrans');

        if ($needsMidtrans) {
            try {
                $midtransGateway = MidtransSdk::resolveGateway();
                MidtransSdk::configure($midtransGateway);
                MidtransSdk::assertConfigured($midtransGateway);
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }

        $ok = 0;
        $fail = 0;

        DecryptSourceContext::run('cron.reconcile_withdrawable_funds', function () use ($rows, $midtransSynchronizer, $ipaymuPayments, &$ok, &$fail): void {
            foreach ($rows as $invoice) {
                $gatewayName = $invoice->paymentGateway?->name;

                try {
                    if ($gatewayName === 'midtrans') {
                        $status = Transaction::status($invoice->invoice_number);
                        $midtransSynchronizer->sync($invoice, $status);
                        $ok++;

                        continue;
                    }

                    if ($gatewayName === 'ipaymu') {
                        $result = $ipaymuPayments->syncInvoiceStatus($invoice);
                        if (! $result['ok']) {
                            $fail++;
                            $this->warn($invoice->invoice_number.': '.$result['message']);

                            continue;
                        }
                        $ok++;

                        continue;
                    }

                    $this->warn($invoice->invoice_number.': unsupported gateway '.$gatewayName);
                    $fail++;
                } catch (\Throwable $e) {
                    $fail++;
                    $this->warn($invoice->invoice_number.': '.$e->getMessage());
                }
            }
        });

        $this->info("Withdrawable reconciliation: {$ok} ok, {$fail} failed/skipped.");

        return self::SUCCESS;
    }
}
