<?php

namespace App\Domain\PaymentGateway\Repositories\Contracts;

use App\Domain\PaymentGateway\Models\Subscription;
use Illuminate\Database\Eloquent\Collection;

interface SubscriptionRepositoryInterface
{
    /**
     * @return Collection<int, Subscription>
     */
    public function all(): Collection;

    public function findOrFail(string $id): Subscription;

    public function create(array $attributes): Subscription;

    public function update(string $id, array $attributes): Subscription;

    public function delete(string $id): void;
}
