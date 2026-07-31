<?php

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\PurchaseStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServicePurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicePurchase>
 */
class ServicePurchaseFactory extends Factory
{
    protected $model = ServicePurchase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'service_id' => Service::factory(),
            'quantity' => 1,
            'display_currency' => 'USD',
            'display_amount' => 10.00,
            'settlement_currency' => 'IDR',
            'settlement_amount' => 180000.00,
            'fx_rate' => 18000.00000000,
            'fx_provider' => 'frankfurter',
            'fx_fetched_at' => now(),
            'status' => PurchaseStatus::Pending,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => PurchaseStatus::Pending]);
    }

    public function paid(): static
    {
        return $this->state(fn (): array => ['status' => PurchaseStatus::Paid]);
    }
}
