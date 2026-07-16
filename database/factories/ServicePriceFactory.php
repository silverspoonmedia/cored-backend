<?php

namespace Database\Factories;

use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServicePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicePrice>
 */
class ServicePriceFactory extends Factory
{
    protected $model = ServicePrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'currency' => 'USD',
            'amount' => 10.00,
        ];
    }

    public function usd(float $amount = 10.00): static
    {
        return $this->state(fn (): array => [
            'currency' => 'USD',
            'amount' => $amount,
        ]);
    }

    public function idr(float $amount = 180000.00): static
    {
        return $this->state(fn (): array => [
            'currency' => 'IDR',
            'amount' => $amount,
        ]);
    }
}
