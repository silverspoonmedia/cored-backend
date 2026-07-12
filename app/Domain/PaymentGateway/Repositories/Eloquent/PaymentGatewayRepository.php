<?php

namespace App\Domain\PaymentGateway\Repositories\Eloquent;

use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Repositories\Contracts\PaymentGatewayRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class PaymentGatewayRepository implements PaymentGatewayRepositoryInterface
{
    public function all(): Collection
    {
        return PaymentGateway::query()->orderBy('priority')->orderBy('display_name')->get();
    }

    public function findOrFail(string $id): PaymentGateway
    {
        return PaymentGateway::query()->findOrFail($id);
    }

    public function create(array $attributes): PaymentGateway
    {
        return PaymentGateway::query()->create($attributes);
    }

    public function update(string $id, array $attributes): PaymentGateway
    {
        $record = $this->findOrFail($id);
        $record->update($attributes);

        return $record->fresh();
    }

    public function delete(string $id): void
    {
        $this->findOrFail($id)->delete();
    }
}
