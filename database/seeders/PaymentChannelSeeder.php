<?php

namespace Database\Seeders;

use App\Domain\PaymentGateway\Support\Routing\PaymentRouteMaterializer;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use Illuminate\Database\Seeder;

class PaymentChannelSeeder extends Seeder
{
    public function run(): void
    {
        if (PaymentGateway::query()->whereIn('name', ['midtrans', 'ipaymu'])->count() < 2) {
            $this->command?->warn('PaymentChannelSeeder skipped: run PaymentGatewaySeeder first.');

            return;
        }

        $stats = app(PaymentRouteMaterializer::class)->sync(dryRun: false);

        $this->command?->info(sprintf(
            'Payment channels synced from routing policy (%d channels, %d routes, %d skipped).',
            $stats['channels'],
            $stats['routes'],
            $stats['skipped'],
        ));
    }
}
