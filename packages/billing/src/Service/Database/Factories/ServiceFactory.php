<?php

namespace App\Domain\Service\Database\Factories;

use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Enums\ServiceStatus;
use App\Domain\Service\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Generic catalog factory — code-agnostic, no product knowledge.
 *
 * Product-specific plans (Archivd storage, Microstock workspaces) live in their
 * own domain factories so this package never imports a product domain.
 *
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
            'plan_kind' => null,
            'storage_bytes' => 0,
            'status' => ServiceStatus::Active,
        ];
    }

    /**
     * A plan the caller fully describes — used by product factories.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function plan(array $attributes): static
    {
        return $this->state(fn (): array => $attributes);
    }

    public function main(): static
    {
        return $this->state(fn (): array => ['plan_kind' => PlanKind::Main]);
    }

    public function addon(): static
    {
        return $this->state(fn (): array => ['plan_kind' => PlanKind::Addon]);
    }

    public function once(): static
    {
        return $this->state(fn (): array => ['billing_interval' => BillingInterval::Once]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => ServiceStatus::Draft]);
    }
}
