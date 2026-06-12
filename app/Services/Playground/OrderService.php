<?php

namespace App\Services\Playground;

use App\Exceptions\Playground\OrderNotEditableException;
use App\Exceptions\Playground\OrderRequiresDetailException;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Business logic for the Order master-detail aggregate.
 *
 * The service owns the rules ("an order needs ≥1 line", "a completed order is
 * frozen") and derives the master totals from the detail lines so the persisted
 * money always matches the items. It delegates the atomic two-table write to
 * OrderRepository, which runs it inside a single DB transaction. HTTP-shape
 * validation stays in the Form Requests; raw queries stay in the repository.
 */
class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $repository,
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
     */
    public function create(array $data): Order
    {
        $details = $this->buildDetailLines($data['details'] ?? []);

        $master = $this->buildMaster($data, $details);
        $master['order_number'] = $this->generateOrderNumber();

        return $this->repository->createWithDetails($master, $details);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $id, array $data): Order
    {
        $order = $this->repository->findOrFail($id);
        $this->assertEditable($order);

        $details = $this->buildDetailLines($data['details'] ?? []);
        $master = $this->buildMaster($data, $details);

        // order_number is immutable after creation.
        unset($master['order_number']);

        return $this->repository->updateWithDetails($id, $master, $details);
    }

    public function delete(string $id): void
    {
        $order = $this->repository->findOrFail($id);
        $this->assertEditable($order);

        $this->repository->deleteWithDetails($id);
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
