<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

/**
 * Dedicated repository for the Order master-detail aggregate.
 *
 * Master-detail persistence spans two tables, so it does NOT belong in the
 * generic EloquentCrudRepository (see docs/crud-architecture.md). Every write
 * here runs inside a single DB transaction: the master row and its detail rows
 * commit together or not at all, so a failure never leaves orphaned data.
 */
interface OrderRepositoryInterface
{
    /**
     * @return Collection<int, Order>
     */
    public function all(): Collection;

    public function findOrFail(string $id): Order;

    /**
     * Persist the master plus its detail lines atomically.
     *
     * @param  array<string, mixed>  $master
     * @param  list<array<string, mixed>>  $details
     */
    public function createWithDetails(array $master, array $details): Order;

    /**
     * Update the master and fully replace its detail lines atomically.
     *
     * @param  array<string, mixed>  $master
     * @param  list<array<string, mixed>>  $details
     */
    public function updateWithDetails(string $id, array $master, array $details): Order;

    public function deleteWithDetails(string $id): void;

    public function count(): int;
}
