<?php

namespace App\Domain\Playground\Services;

use App\Domain\PaymentGateway\Contracts\InvoiceAttachableServiceInterface;
use App\Domain\PaymentGateway\Services\Payment\InvoiceActiveCheckoutCancellationService;
use App\Domain\Playground\Exceptions\OrderNotEditableException;
use App\Domain\Playground\Exceptions\OrderRequiresDetailException;
use App\Domain\Playground\Models\Order;
use App\Domain\Playground\Repositories\Contracts\OrderRepositoryInterface;
use App\Domain\Approval\Services\ApprovalService;
use App\Domain\Playground\Support\Orders\DirectAttachmentUploadService;
use App\Domain\Playground\Support\Orders\OrderAttachmentStorage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Business logic for the Order master-detail aggregate.
 *
 * Attachments are staged on object storage via browser-direct uploads before
 * create/update. The service resolves completed upload sessions into metadata
 * rows and compensates by deleting freshly staged objects if the DB write fails.
 */
class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $repository,
        private readonly OrderAttachmentStorage $attachments,
        private readonly DirectAttachmentUploadService $directUploads,
        private readonly ApprovalService $approvalService,
        private readonly InvoiceAttachableServiceInterface $invoiceAttachable,
        private readonly InvoiceActiveCheckoutCancellationService $checkoutCancellation,
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
     * @param  list<string>  $stagedUploadIds
     */
    public function create(array $data, array $stagedUploadIds = []): Order
    {
        $details = $this->buildDetailLines($data['details'] ?? []);
        $stored = $this->stampOwner($this->directUploads->resolveStaged($stagedUploadIds));

        $master = $this->buildMaster($data, $details);
        $master['order_number'] = $this->generateOrderNumber();

        try {
            $order = $this->repository->createWithDetails($master, $details, $stored);

            $this->invoiceAttachable->createPendingInvoiceForOrder($order, null);

            try {
                $this->approvalService->initiate($order, 'ORDER');
            } catch (\Exception $e) {
                \Log::warning('Failed to initiate approval for order', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $order;
        } catch (Throwable $e) {
            $this->attachments->deleteMany($stored);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $stagedUploadIds
     */
    public function update(string $id, array $data, array $stagedUploadIds = []): Order
    {
        $order = $this->repository->findOrFail($id);
        $this->assertEditable($order);

        $details = $this->buildDetailLines($data['details'] ?? []);
        $master = $this->buildMaster($data, $details);

        unset($master['order_number']);

        $invoice = $order->invoice;
        $shouldAbandonCheckout = $invoice !== null
            && $invoice->status === 'pending'
            && $this->checkoutCancellation->hasCheckoutSession($invoice)
            && (float) $invoice->customer_gross_total !== (float) $master['grand_total'];

        if ($shouldAbandonCheckout) {
            $this->checkoutCancellation->abandonActiveCheckout($invoice);
        }

        $existing = $order->attachments
            ->map(fn ($a): array => $a->only(['user_id', 'disk', 'path', 'name', 'size', 'mime']))
            ->all();
        [$kept, $removed] = $this->partitionByKeep($existing, $this->resolveKeepPaths($data));

        $stored = $this->stampOwner($this->directUploads->resolveStaged($stagedUploadIds));
        $desired = array_values([...$kept, ...$stored]);

        try {
            $updated = $this->repository->updateWithDetails($id, $master, $details, $desired);
        } catch (Throwable $e) {
            $this->attachments->deleteMany($stored);

            throw $e;
        }

        $this->invoiceAttachable->syncOrderInvoiceAmountIfPending($updated);

        $this->attachments->deleteMany($removed);

        return $updated;
    }

    public function delete(string $id): void
    {
        $order = $this->repository->findOrFail($id);
        $this->assertEditable($order);

        $attachments = $order->attachments
            ->map(fn ($a): array => $a->only(['disk', 'path']))
            ->all();

        $this->invoiceAttachable->deletePendingInvoiceForOrder($order);

        $this->repository->deleteWithDetails($id);

        $this->attachments->deleteMany($attachments);
    }

    /**
     * @param  list<string>  $ids
     */
    public function deleteMany(array $ids): void
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return;
        }

        $orders = $this->repository->findManyOrFail($ids);

        foreach ($orders as $order) {
            $this->assertEditable($order);
        }

        $attachments = $orders
            ->flatMap(fn (Order $order) => $order->attachments)
            ->map(fn ($a): array => $a->only(['disk', 'path']))
            ->all();

        $this->repository->deleteManyWithDetails($ids);

        $this->attachments->deleteMany($attachments);
    }

    /**
     * @param  list<array<string, mixed>>  $stored
     * @return list<array<string, mixed>>
     */
    private function stampOwner(array $stored): array
    {
        $userId = auth()->id();

        return array_map(
            fn (array $row): array => $row + ['user_id' => $userId ?? $row['user_id'] ?? null],
            $stored,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>|null
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

            if (! empty($detail['id'])) {
                $line['id'] = $detail['id'];
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
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
