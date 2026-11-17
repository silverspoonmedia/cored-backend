<?php

namespace App\Domain\PaymentGateway\Repositories\Contracts;

use App\Domain\PaymentGateway\Models\PaymentGateway;
use Illuminate\Database\Eloquent\Collection;

interface PaymentGatewayRepositoryInterface
{
    /**
     * @return Collection<int, PaymentGateway>
     */
    public function all(): Collection;

    public function findOrFail(string $id): PaymentGateway;

    public function create(array $attributes): PaymentGateway;

    public function update(string $id, array $attributes): PaymentGateway;

    public function delete(string $id): void;
}
