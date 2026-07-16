<?php

namespace Database\Factories;

use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\ServiceStatus;
use App\Domain\Service\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'svc.'.Str::lower(Str::random(8)),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'billing_interval' => BillingInterval::Monthly,
            'fulfillment_target' => null,
            'entitlement' => null,
            'status' => ServiceStatus::Active,
        ];
    }

    public function fileHostingMonthly(): static
    {
        return $this->state(fn (): array => [
            'code' => 'file-hosting.subscription.monthly',
            'name' => 'File Hosting Subscription',
            'description' => 'Unlimited storage, billed monthly.',
            'billing_interval' => BillingInterval::Monthly,
            'fulfillment_target' => 'file_hosting',
            'entitlement' => 'unlimited_quota',
            'status' => ServiceStatus::Active,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => ServiceStatus::Draft]);
    }
}
