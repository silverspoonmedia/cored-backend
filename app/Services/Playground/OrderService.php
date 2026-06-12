<?php

namespace App\Services\Playground;

use App\Exceptions\Playground\OrderNotEditableException;
use App\Exceptions\Playground\OrderRequiresDetailException;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Support\Orders\OrderAttachmentStorage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;

/**
 * Business logic for the Order master-detail aggregate.
 *
 * The service owns the rules ("an order needs ≥1 line", "a completed order is
 * frozen") and derives the master totals from the detail lines so the persisted
 * money always matches the items. It delegates the atomic two-table write to
 * OrderRepository, which runs it inside a single DB transaction. HTTP-shape
 * validation stays in the Form Requests; raw queries stay in the repository.
 *
 * Attachments live on a file disk, NOT in the DB transaction. The service
 * stores files first, then persists their metadata; if the DB write fails it
 * compensates by deleting the just-uploaded files so no orphaned objects leak.
 * On a successful update it removes the files the caller dropped.
 */
class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $repository,
        private readonly OrderAttachmentStorage $attachments,
    ) {}

    public function list(): Collection
    {
        return $this->repository->all();
    }

    public function get(string $id): Order
    {
        return $this->repository->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $files
     */
    public function create(array $data, array $files = []): Order
    {
        $details = $this->buildDetailLines($data['details'] ?? []);

        // Upload files BEFORE the DB write; if the transaction throws, delete
        // the freshly stored objects so a failed order leaves no orphaned files.
        $stored = $this->attachments->storeMany($files);

        $master = $this->buildMaster($data, $details);
        $master['order_number'] = $this->generateOrderNumber();
        $master['attachment'] = $stored;

        try {
            return $this->repository->createWithDetails($master, $details);
        } catch (Throwable $e) {
            $this->attachments->deleteMany($stored);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $files
     */
    public function update(string $id, array $data, array $files = []): Order
    {
        $order = $this->repository->findOrFail($id);
        $this->assertEditable($order);

        $details = $this->buildDetailLines($data['details'] ?? []);
        $master = $this->buildMaster($data, $details);

        // order_number is immutable after creation.
        unset($master['order_number']);

        // Reconcile attachments: keep the ones the caller asked to keep, append
        // the newly uploaded files. Anything dropped is removed AFTER the DB
        // commit so a rollback never loses a file that is still referenced.
        $existing = $order->attachment ?? [];
        [$kept, $removed] = $this->partitionByKeep($existing, $this->resolveKeepPaths($data));

        $stored = $this->attachments->storeMany($files);
        $master['attachment'] = array_values([...$kept, ...$stored]);

        try {
            $updated = $this->repository->updateWithDetails($id, $master, $details);
        } catch (Throwable $e) {
            $this->attachments->deleteMany($stored);

            throw $e;
        }

        // Committed — now it is safe to purge the files the caller dropped.
        $this->attachments->deleteMany($removed);

        return $updated;
    }

    public function delete(string $id): void
    {
        $order = $this->repository->findOrFail($id);
        $this->assertEditable($order);

        $attachments = $order->attachment ?? [];

        $this->repository->deleteWithDetails($id);

        // DB row gone — drop its files. (Done after commit; a stored file with
        // no DB row is a harmless leak, a missing file with a live row is not.)
        $this->attachments->deleteMany($attachments);
    }

    /**
     * Resolve the keep-list from request data, reconciling the multipart edge
     * case: an empty `keep_attachments` array cannot be encoded over
     * multipart/form-data (zero fields = absent key). Clients that own the
     * attachment set send `attachments_managed=1`; when that marker is truthy
     * and no keep-list arrived, "absent" means **keep none**, not keep all.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>|null null = keep all; [] = drop all; list = keep those
     */
    private function resolveKeepPaths(array $data): ?array
    {
        if (array_key_exists('keep_attachments', $data) && $data['keep_attachments'] !== null) {
            return array_values($data['keep_attachments']);
        }

        if (filter_var($data['attachments_managed'] ?? false, FILTER_VALIDATE_BOOL)) {
            return [];
        }

        return null;
    }

    /**
     * Split existing attachments into [kept, removed] using the caller's
     * keep-list (array of paths). A null keep-list means "keep all" (e.g. a
     * metadata-only edit that does not touch files); an empty array drops all.
     *
     * @param  list<array<string, mixed>>  $existing
     * @param  list<string>|null  $keepPaths
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function partitionByKeep(array $existing, ?array $keepPaths): array
    {
        if ($keepPaths === null) {
            return [$existing, []];
        }

        $kept = [];
        $removed = [];

        foreach ($existing as $attachment) {
            if (in_array($attachment['path'] ?? null, $keepPaths, true)) {
                $kept[] = $attachment;
            } else {
                $removed[] = $attachment;
            }
        }

        return [$kept, $removed];
    }

    /**
     * Normalise + compute each line's subtotal. Rejects an empty line set: an
     * order with no items is meaningless and would strand an empty master.
     *
     * @param  array<int, array<string, mixed>>  $rawDetails
     * @return list<array<string, mixed>>
     */
    private function buildDetailLines(array $rawDetails): array
    {
        if ($rawDetails === []) {
            throw new OrderRequiresDetailException;
        }

        $lines = [];

        foreach ($rawDetails as $detail) {
            $quantity = (int) ($detail['quantity'] ?? 0);
            $unitPrice = round((float) ($detail['unit_price'] ?? 0), 2);
            $discount = round((float) ($detail['discount'] ?? 0), 2);

            $subtotal = round(($quantity * $unitPrice) - $discount, 2);

            $line = [
                'product_name' => $detail['product_name'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'subtotal' => max($subtotal, 0),
                'notes' => $detail['notes'] ?? null,
            ];

            // Carry an existing line id through so the repository can update it
            // in place (ID-preserving sync) instead of recreating the row. New
            // lines simply omit the id and get a fresh UUID on insert.
            if (! empty($detail['id'])) {
                $line['id'] = $detail['id'];
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Derive master totals from the lines so persisted money matches the items.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $details
     * @return array<string, mixed>
     */
    private function buildMaster(array $data, array $details): array
    {
        $subtotal = round(array_sum(array_column($details, 'subtotal')), 2);
        $discount = round((float) ($data['discount'] ?? 0), 2);
        $tax = round((float) ($data['tax'] ?? 0), 2);
        $grandTotal = max(round($subtotal - $discount + $tax, 2), 0);

        return [
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'customer_phone' => $data['customer_phone'] ?? null,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'grand_total' => $grandTotal,
            'status' => $data['status'] ?? 'pending',
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function assertEditable(Order $order): void
    {
        if ($order->status === 'completed') {
            throw new OrderNotEditableException;
        }
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
