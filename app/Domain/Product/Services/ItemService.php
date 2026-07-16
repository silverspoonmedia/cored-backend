<?php

namespace App\Domain\Product\Services;

use App\Domain\Product\Enums\ItemStatus;
use App\Domain\Product\Exceptions\DuplicateSkuException;
use App\Domain\Product\Models\Item;
use App\Repositories\Contracts\EloquentCrudRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ItemService
{
    public function __construct(
        private readonly EloquentCrudRepositoryInterface $repository,
    ) {}

    public function list(): Collection
    {
        return $this->repository->all(Item::class);
    }

    public function get(string $id): Model
    {
        return $this->repository->findOrFail(Item::class, $id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $payload = $this->normalize($data);
        $this->assertSkuAvailable($payload['sku']);

        return $this->repository->create(Item::class, $payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $id, array $data): Model
    {
        $payload = $this->normalize($data);

        if (isset($payload['sku'])) {
            $this->assertSkuAvailable($payload['sku'], $id);
        }

        return $this->repository->update(Item::class, $id, $payload);
    }

    public function delete(string $id): void
    {
        $this->repository->delete(Item::class, $id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $payload = array_intersect_key($data, array_flip([
            'sku',
            'name',
            'description',
            'unit_price_cents',
            'status',
        ]));

        if (isset($payload['sku']) && is_string($payload['sku'])) {
            $payload['sku'] = strtoupper(trim($payload['sku']));
        }

        if (isset($payload['status']) && is_string($payload['status'])) {
            $payload['status'] = ItemStatus::from($payload['status'])->value;
        }

        return $payload;
    }

    private function assertSkuAvailable(string $sku, ?string $ignoreId = null): void
    {
        $query = Item::query()->where('sku', $sku);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw new DuplicateSkuException;
        }
    }
}
