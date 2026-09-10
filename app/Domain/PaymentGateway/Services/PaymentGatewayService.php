<?php

namespace App\Domain\PaymentGateway\Services;

use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Repositories\Contracts\PaymentGatewayRepositoryInterface;
use App\Domain\PaymentGateway\Support\PaymentRealtime;
use Illuminate\Database\Eloquent\Collection;

class PaymentGatewayService
{
    public function __construct(
        private readonly PaymentGatewayRepositoryInterface $repository,
        private readonly PaymentRealtime $realtime,
    ) {}

    /**
     * @return Collection<int, PaymentGateway>
     */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    public function get(string $id): PaymentGateway
    {
        return $this->repository->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PaymentGateway
    {
        $gateway = $this->repository->create($data);

        $this->realtime->gatewayChanged('created', (string) $gateway->id);

        return $gateway;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $id, array $data): PaymentGateway
    {
        $gateway = $this->repository->update($id, $data);

        $this->realtime->gatewayChanged('updated', (string) $gateway->id);

        return $gateway;
    }

    public function delete(string $id): void
    {
        $this->repository->delete($id);

        $this->realtime->gatewayChanged('deleted', (string) $id);
    }
}
