<?php

namespace App\Domain\PaymentGateway\Console\Commands;

use App\Domain\PaymentGateway\Support\Routing\PaymentRouteMaterializer;
use Illuminate\Console\Command;

class PaymentsSyncRoutingPolicyCommand extends Command
{
    protected $signature = 'payments:sync-routing-policy {--dry-run : Show what would change without writing}';

    protected $description = 'Materialize Routing_Price.yaml into payment_channels and payment_channel_routes.';

    public function handle(PaymentRouteMaterializer $materializer): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — no database writes.');
        }

        $stats = $materializer->sync($dryRun);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Channels', $stats['channels']],
                ['Routes', $stats['routes']],
                ['Skipped', $stats['skipped']],
            ],
        );

        $this->info($dryRun
            ? 'Dry run complete.'
            : 'Routing policy synced to database.');

        return self::SUCCESS;
    }
}
