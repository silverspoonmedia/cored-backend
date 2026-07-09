<?php

namespace App\Domain\Playground\Repositories\Contracts;

use App\Domain\Playground\Models\Order;
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
     * Fetch many orders (with details + attachments) by id. Throws when any id
     * is missing, so a partial mass action never silently skips a row.
     *
     * @param  list<string>  $ids
     * @return Collection<int, Order>
     */
    public function findManyOrFail(array $ids): Collection;

    /**
     * Persist the master plus its detail lines and attachment rows atomically.
     *
     * @param  array<string, mixed>  $master
     * @param  list<array<string, mixed>>  $details
     * @param  list<array<string, mixed>>  $attachments  desired attachment metadata rows ({path,name,size,mime})
     */
    public function createWithDetails(array $master, array $details, array $attachments = []): Order;

    /**
     * Update the master, fully replace its detail lines, and reconcile its
     * attachment rows (by `path`) atomically.
     *
     * @param  array<string, mixed>  $master
     * @param  list<array<string, mixed>>  $details
     * @param  list<array<string, mixed>>  $attachments  desired attachment metadata rows ({path,name,size,mime})
     */
    public function updateWithDetails(string $id, array $master, array $details, array $attachments = []): Order;

    public function deleteWithDetails(string $id): void;

    /**
     * Delete many masters plus their details and attachment rows atomically.
     *
     * @param  list<string>  $ids
     */
    public function deleteManyWithDetails(array $ids): void;

    public function count(): int;
}
