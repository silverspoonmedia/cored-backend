<?php

namespace App\Domain\Service\Console\Commands;

use App\Domain\Service\Services\ServiceAddonRenewalService;
use Illuminate\Console\Command;

class ProcessServiceAddonRenewalsCommand extends Command
{
    protected $signature = 'service:process-addon-renewals {--currency=USD : Display currency for line prices}';

    protected $description = 'Create pending full-stack addon renewal invoices for due anniversaries';

    public function handle(ServiceAddonRenewalService $renewals): int
    {
        $currency = strtoupper((string) $this->option('currency'));
        $result = $renewals->createDueRenewals($currency);

        $this->info("Addon renewals created: {$result['created']}; skipped: {$result['skipped']}");

        return self::SUCCESS;
    }
}
