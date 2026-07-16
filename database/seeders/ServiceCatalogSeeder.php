<?php

namespace Database\Seeders;

use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\ServiceStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServicePrice;
use Illuminate\Database\Seeder;

/**
 * POC catalog: File Hosting monthly subscription at USD 10 (unlimited storage).
 */
class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $service = Service::query()->updateOrCreate(
            ['code' => 'file-hosting.subscription.monthly'],
            [
                'name' => 'File Hosting Subscription',
                'description' => 'Unlimited File Hosting storage, billed monthly.',
                'billing_interval' => BillingInterval::Monthly,
                'fulfillment_target' => 'file_hosting',
                'entitlement' => 'unlimited_quota',
                'status' => ServiceStatus::Active,
            ],
        );

        ServicePrice::query()->updateOrCreate(
            [
                'service_id' => $service->id,
                'currency' => 'USD',
            ],
            [
                'amount' => 10.00,
            ],
        );
    }
}
