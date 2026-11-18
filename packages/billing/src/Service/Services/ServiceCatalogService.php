<?php

namespace App\Domain\Service\Services;

use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\PlanKind;
use App\Domain\Service\Enums\ServiceStatus;
use App\Domain\Service\Exceptions\DuplicateServiceCodeException;
use App\Domain\Service\Exceptions\ServiceException;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServicePrice;
use App\Domain\Service\Support\ServiceRealtime;
use App\Repositories\Contracts\EloquentCrudRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ServiceCatalogService
{
    public function __construct(
        private readonly EloquentCrudRepositoryInterface $repository,
        private readonly ServiceRealtime $realtime,
    ) {}

    /**
     * @return Collection<int, Service>
     */
    public function list(): Collection
    {
        return Service::query()
            ->with('prices')
            ->orderBy('name')
            ->get();
    }

    public function get(string $id): Service
    {
        /** @var Service $service */
        $service = $this->repository->findOrFail(Service::class, $id);
        $service->load('prices');

        return $service;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Service
    {
        $payload = $this->normalize($data);
        $this->assertCodeAvailable($payload['code']);
        $prices = $this->extractPrices($data);

        return DB::transaction(function () use ($payload, $prices): Service {
            /** @var Service $service */
            $service = $this->repository->create(Service::class, $payload);
            $this->syncPrices($service, $prices);

            $service = $service->load('prices');
            $this->realtime->catalogChanged('created', $service->id);

            return $service;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $id, array $data): Service
    {
        $payload = $this->normalize($data);

        if (isset($payload['code'])) {
            $this->assertCodeAvailable($payload['code'], $id);
        }

        $hasPrices = array_key_exists('prices', $data);
        $prices = $hasPrices ? $this->extractPrices($data) : null;

        return DB::transaction(function () use ($id, $payload, $hasPrices, $prices): Service {
            /** @var Service $service */
            $service = $this->repository->update(Service::class, $id, $payload);

            if ($hasPrices) {
                $this->syncPrices($service, $prices ?? []);
            }

            $service = $service->load('prices');
            $this->realtime->catalogChanged('updated', $service->id);

            return $service;
        });
    }

    public function delete(string $id): void
    {
        $this->repository->delete(Service::class, $id);
        $this->realtime->catalogChanged('deleted', $id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $payload = array_intersect_key($data, array_flip([
            'code',
            'name',
            'description',
            'billing_interval',
            'fulfillment_target',
            'entitlement',
            'plan_kind',
            'storage_bytes',
            'status',
        ]));

        if (isset($payload['code']) && is_string($payload['code'])) {
            $payload['code'] = strtolower(trim($payload['code']));
        }

        if (isset($payload['billing_interval']) && is_string($payload['billing_interval'])) {
            $payload['billing_interval'] = BillingInterval::from($payload['billing_interval'])->value;
        }

        if (isset($payload['status']) && is_string($payload['status'])) {
            $payload['status'] = ServiceStatus::from($payload['status'])->value;
        }

        if (isset($payload['plan_kind']) && is_string($payload['plan_kind'])) {
            $payload['plan_kind'] = $payload['plan_kind'] === ''
                ? null
                : PlanKind::from($payload['plan_kind'])->value;
        }

        if (array_key_exists('storage_bytes', $payload)) {
            $payload['storage_bytes'] = max(0, (int) $payload['storage_bytes']);
        }

        foreach (['fulfillment_target', 'entitlement', 'description', 'plan_kind'] as $nullable) {
            if (array_key_exists($nullable, $payload) && $payload[$nullable] === '') {
                $payload[$nullable] = null;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{currency: string, amount: float}>
     */
    private function extractPrices(array $data): array
    {
        $raw = $data['prices'] ?? [];

        if (! is_array($raw)) {
            throw new ServiceException(__('api/services.prices_invalid'));
        }

        $out = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $currency = strtoupper(trim((string) ($row['currency'] ?? '')));
            $amount = $row['amount'] ?? null;

            if ($currency === '' || ! is_numeric($amount)) {
                throw new ServiceException(__('api/services.prices_invalid'));
            }

            $out[] = [
                'currency' => $currency,
                'amount' => round((float) $amount, 2),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{currency: string, amount: float}>  $prices
     */
    private function syncPrices(Service $service, array $prices): void
    {
        $service->prices()->delete();

        foreach ($prices as $price) {
            ServicePrice::query()->create([
                'service_id' => $service->id,
                'currency' => $price['currency'],
                'amount' => $price['amount'],
            ]);
        }
    }

    private function assertCodeAvailable(string $code, ?string $ignoreId = null): void
    {
        $query = Service::query()->where('code', $code);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw new DuplicateServiceCodeException;
        }
    }
}
