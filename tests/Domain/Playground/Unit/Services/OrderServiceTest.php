<?php

namespace Tests\Domain\Playground\Unit\Services;

use App\Domain\Approval\Services\ApprovalService;
use App\Domain\PaymentGateway\Contracts\InvoiceAttachableServiceInterface;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Services\Payment\InvoiceActiveCheckoutCancellationService;
use App\Domain\Playground\Exceptions\OrderNotEditableException;
use App\Domain\Playground\Exceptions\OrderRequiresDetailException;
use App\Domain\Playground\Models\AttachmentUpload;
use App\Domain\Playground\Models\Order;
use App\Domain\Playground\Repositories\Contracts\OrderRepositoryInterface;
use App\Domain\Playground\Services\OrderService;
use App\Domain\Playground\Support\Orders\DirectAttachmentUploadService;
use App\Domain\Playground\Support\Orders\OrderAttachmentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use Tests\Fakes\FakeDirectAttachmentUploadService;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderRepositoryInterface&MockInterface $repository;

    private OrderService $service;

    private string $paymentGatewayId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(OrderAttachmentStorage::DISK);
        $this->app->instance(
            DirectAttachmentUploadService::class,
            new FakeDirectAttachmentUploadService(new OrderAttachmentStorage),
        );

        $this->paymentGatewayId = PaymentGateway::query()->create([
            'name' => 'midtrans',
            'display_name' => 'Midtrans',
            'is_active' => true,
            'priority' => 10,
        ])->id;

        $this->repository = Mockery::mock(OrderRepositoryInterface::class);
        $approvalService = Mockery::mock(ApprovalService::class);
        $approvalService->shouldReceive('initiate')->byDefault();
        $invoiceAttachable = Mockery::mock(InvoiceAttachableServiceInterface::class);
        $invoiceAttachable->shouldReceive('createPendingInvoiceForOrder')->byDefault();
        $invoiceAttachable->shouldReceive('syncOrderInvoiceAmountIfPending')->byDefault();
        $invoiceAttachable->shouldReceive('deletePendingInvoiceForOrder')->byDefault();
        $checkoutCancellation = Mockery::mock(InvoiceActiveCheckoutCancellationService::class);
        $checkoutCancellation->shouldReceive('cancelActiveCheckoutForOrder')->byDefault();
        $this->service = new OrderService(
            $this->repository,
            new OrderAttachmentStorage,
            app(DirectAttachmentUploadService::class),
            $approvalService,
            $invoiceAttachable,
            $checkoutCancellation,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_create_rejects_empty_detail_set(): void
    {
        $this->repository->shouldNotReceive('createWithDetails');

        $this->expectException(OrderRequiresDetailException::class);

        $this->service->create([
            'customer_name' => 'Jane',
            'customer_email' => 'jane@example.com',
            'payment_gateway_id' => $this->paymentGatewayId,
            'details' => [],
        ]);
    }

    public function test_create_computes_totals_and_generates_order_number(): void
    {
        $this->repository
            ->shouldReceive('createWithDetails')
            ->once()
            ->with(
                Mockery::on(function (array $master): bool {
                    return $master['subtotal'] === 240.0
                        && $master['grand_total'] === 225.0
                        && str_starts_with((string) $master['order_number'], 'ORD-');
                }),
                Mockery::on(fn (array $details): bool => count($details) === 2 && $details[0]['subtotal'] === 200.0),
                Mockery::on(fn (array $attachments): bool => $attachments === []),
            )
            ->andReturn(new Order);

        $this->service->create([
            'customer_name' => 'Jane',
            'customer_email' => 'jane@example.com',
            'payment_gateway_id' => $this->paymentGatewayId,
            'discount' => 20,
            'tax' => 5,
            'details' => [
                ['product_name' => 'A', 'quantity' => 2, 'unit_price' => 100, 'discount' => 0],
                ['product_name' => 'B', 'quantity' => 1, 'unit_price' => 50, 'discount' => 10],
            ],
        ]);
    }

    public function test_create_deletes_staged_files_when_db_write_fails(): void
    {
        $upload = AttachmentUpload::factory()->completed()->create();
        Storage::disk($upload->disk)->put($upload->path, 'payload');

        $this->repository
            ->shouldReceive('createWithDetails')
            ->once()
            ->andThrow(new \RuntimeException('db boom'));

        try {
            $this->service->create([
                'customer_name' => 'Jane',
                'customer_email' => 'jane@example.com',
                'payment_gateway_id' => $this->paymentGatewayId,
                'details' => [['product_name' => 'A', 'quantity' => 1, 'unit_price' => 10]],
            ], [$upload->id]);
            $this->fail('Expected the DB failure to propagate.');
        } catch (\RuntimeException) {
        }

        Storage::disk($upload->disk)->assertMissing($upload->path);
    }

    public function test_update_rejects_completed_order(): void
    {
        $order = new Order(['status' => 'completed']);

        $this->repository
            ->shouldReceive('findOrFail')
            ->once()
            ->with('order-id')
            ->andReturn($order);

        $this->repository->shouldNotReceive('updateWithDetails');

        $this->expectException(OrderNotEditableException::class);

        $this->service->update('order-id', [
            'customer_name' => 'Jane',
            'customer_email' => 'jane@example.com',
            'details' => [['product_name' => 'A', 'quantity' => 1, 'unit_price' => 10]],
        ]);
    }

    public function test_delete_rejects_completed_order(): void
    {
        $order = new Order(['status' => 'completed']);

        $this->repository
            ->shouldReceive('findOrFail')
            ->once()
            ->with('order-id')
            ->andReturn($order);

        $this->repository->shouldNotReceive('deleteWithDetails');

        $this->expectException(OrderNotEditableException::class);

        $this->service->delete('order-id');
    }
}
