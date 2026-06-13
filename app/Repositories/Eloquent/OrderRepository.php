<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent implementation of the Order master-detail aggregate.
 *
 * The create/update/delete paths wrap every multi-table write in
 * DB::transaction(): the master row and its detail rows commit together or roll
 * back together. If inserting any detail line throws, the whole unit of work is
 * rolled back, so the database never keeps a master without its lines (no
 * orphaned data). Pure data access only — business rules live in OrderService.
 */
class OrderRepository implements OrderRepositoryInterface
{
    public function all(): Collection
    {
        return Order::query()
            ->with('attachments')
            ->withCount('details')
            ->latest()
            ->get();
    }

    public function findOrFail(string $id): Order
    {
        return Order::query()
            ->with('details', 'attachments')
            ->findOrFail($id);
    }

    public function findManyOrFail(array $ids): Collection
    {
        $ids = array_values(array_unique($ids));

        $orders = Order::query()
            ->with('details', 'attachments')
            ->findMany($ids);

        if ($orders->count() !== count($ids)) {
            throw (new ModelNotFoundException)->setModel(Order::class, $ids);
        }

        return $orders;
    }

    public function createWithDetails(array $master, array $details, array $attachments = []): Order
    {
        return DB::transaction(function () use ($master, $details, $attachments): Order {
            $order = Order::query()->create($master);

            $order->details()->createMany($details);

            if ($attachments !== []) {
                $order->attachments()->createMany($attachments);
            }

            return $order->load('details', 'attachments');
        });
    }

    public function updateWithDetails(string $id, array $master, array $details, array $attachments = []): Order
    {
        return DB::transaction(function () use ($id, $master, $details, $attachments): Order {
            $order = Order::query()->findOrFail($id);

            $order->update($master);

            // ID-preserving sync (NOT delete-all + recreate): a line that carries
            // an existing id is updated in place so its UUID — and any external
            // reference to it — survives the edit. New lines (no/unknown id) are
            // inserted, and lines absent from the payload are removed. All inside
            // the transaction, so a mid-sync failure rolls back to the prior set.
            $existingIds = $order->details()->pluck('id')->all();
            $keptIds = [];

            foreach ($details as $line) {
                $lineId = $line['id'] ?? null;
                $attributes = Arr::except($line, ['id']);

                if ($lineId !== null && in_array($lineId, $existingIds, true)) {
                    $order->details()->whereKey($lineId)->update($attributes);
                    $keptIds[] = $lineId;

                    continue;
                }

                // No id, or an id that does not belong to this order: treat as a
                // new line and let HasUuids assign the key (never trust a foreign id).
                $keptIds[] = $order->details()->create($attributes)->id;
            }

            $order->details()->whereKeyNot($keptIds)->delete();

            $this->syncAttachments($order, $attachments);

            return $order->load('details', 'attachments');
        });
    }

    /**
     * Reconcile the order's attachment rows against the desired set by `path`
     * (the stable disk key). Rows whose path is no longer desired are deleted,
     * brand-new paths are inserted, and surviving rows are left untouched so
     * their UUIDs persist. File I/O (the actual upload/delete) is owned by
     * OrderService; here we only mirror the metadata inside the transaction.
     *
     * @param  list<array<string, mixed>>  $desired
     */
    private function syncAttachments(Order $order, array $desired): void
    {
        $desiredPaths = array_column($desired, 'path');

        // Drop rows the caller no longer wants.
        $order->attachments()->whereNotIn('path', $desiredPaths)->delete();

        // Insert rows for paths that are not persisted yet.
        $existingPaths = $order->attachments()->pluck('path')->all();

        $new = array_values(array_filter(
            $desired,
            fn (array $row): bool => ! in_array($row['path'], $existingPaths, true),
        ));

        if ($new !== []) {
            $order->attachments()->createMany($new);
        }
    }

    public function deleteWithDetails(string $id): void
    {
        DB::transaction(function () use ($id): void {
            $order = Order::query()->findOrFail($id);

            // Explicit child delete keeps the intent clear and stays correct
            // even if the FK cascade is ever relaxed; all rows go in one tx.
            $order->details()->delete();
            $order->attachments()->delete();
            $order->delete();
        });
    }

    public function deleteManyWithDetails(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        DB::transaction(function () use ($ids): void {
            $orders = Order::query()->with('details', 'attachments')->findMany($ids);

            // Explicit child delete keeps the intent clear and stays correct
            // even if the FK cascade is ever relaxed; every row goes in one tx.
            foreach ($orders as $order) {
                $order->details()->delete();
                $order->attachments()->delete();
                $order->delete();
            }
        });
    }

    public function count(): int
    {
        return Order::query()->count();
    }
}
