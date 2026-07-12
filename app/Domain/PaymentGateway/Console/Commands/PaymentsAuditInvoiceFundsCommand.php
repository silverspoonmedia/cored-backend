<?php

namespace App\Domain\PaymentGateway\Console\Commands;

use App\Domain\PaymentGateway\Enums\DisbursementStatus;
use App\Domain\PaymentGateway\Models\Invoice;
use Illuminate\Console\Command;

class PaymentsAuditInvoiceFundsCommand extends Command
{
    protected $signature = 'payments:audit-invoice-funds {--json : Print JSON summary}';

    protected $description = 'Summarize invoices by withdrawable / disbursement buckets (ops + reporting).';

    public function handle(): int
    {
        $summary = [
            'withdrawable' => (int) Invoice::query()->whereNotNull('withdrawable_at')->count(),
            'paid_not_withdrawable' => (int) Invoice::query()
                ->whereIn('status', ['paid', 'partially_refunded'])
                ->whereNull('withdrawable_at')
                ->count(),
            'disbursement' => [
                DisbursementStatus::None->value => (int) Invoice::query()->where('disbursement_status', DisbursementStatus::None)->count(),
                DisbursementStatus::Eligible->value => (int) Invoice::query()->where('disbursement_status', DisbursementStatus::Eligible)->count(),
                DisbursementStatus::Processing->value => (int) Invoice::query()->where('disbursement_status', DisbursementStatus::Processing)->count(),
                DisbursementStatus::Completed->value => (int) Invoice::query()->where('disbursement_status', DisbursementStatus::Completed)->count(),
                DisbursementStatus::Failed->value => (int) Invoice::query()->where('disbursement_status', DisbursementStatus::Failed)->count(),
            ],
        ];

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->table(
            ['Bucket', 'Count'],
            [
                ['withdrawable_at set (dana siap cair)', $summary['withdrawable']],
                ['paid / partially_refunded, not withdrawable yet', $summary['paid_not_withdrawable']],
                ['disbursement: none', $summary['disbursement']['none']],
                ['disbursement: eligible (antrian payout)', $summary['disbursement']['eligible']],
                ['disbursement: processing', $summary['disbursement']['processing']],
                ['disbursement: completed', $summary['disbursement']['completed']],
                ['disbursement: failed', $summary['disbursement']['failed']],
            ]
        );

        return self::SUCCESS;
    }
}
