<?php

namespace Tests\Unit\Services\Playground;

use App\Exceptions\Playground\OrderNotEditableException;
use App\Exceptions\Playground\OrderRequiresDetailException;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\Playground\OrderService;
use App\Support\Orders\OrderAttachmentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    private OrderRepositoryInterface&MockInterface $repository;

    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Real storage class against a fake disk — keeps the file I/O honest
        // while the repository (DB) stays mocked for unit isolation.
        Storage::fake(OrderAttachmentStorage::DISK);

        $this->repository = Mockery::mock(OrderRepositoryInterface::class);
        $this->service = new OrderService($this->repository, new OrderAttachmentStorage);
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
                    // subtotal = (2*100-0)+(1*50-10)=240, grand = 240-20+5=225
                    return $master['subtotal'] === 240.0
                        && $master['grand_total'] === 225.0
                        && str_starts_with((string) $master['order_number'], 'ORD-');
                }),
                Mockery::on(fn (array $details): bool => count($details) === 2 && $details[0]['subtotal'] === 200.0),
            )
            ->andReturn(new Order);

        $this->service->create([
            'customer_name' => 'Jane',
            'customer_email' => 'jane@example.com',
            'discount' => 20,
            'tax' => 5,
            'details' => [
                ['product_name' => 'A', 'quantity' => 2, 'unit_price' => 100, 'discount' => 0],
                ['product_name' => 'B', 'quantity' => 1, 'unit_price' => 50, 'discount' => 10],
            ],
        ]);
    }

    public function test_create_deletes_uploaded_files_when_db_write_fails(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->repository
            ->shouldReceive('createWithDetails')
            ->once()
            ->andThrow(new \RuntimeException('db boom'));

        try {
            $this->service->create([
                'customer_name' => 'Jane',
                'customer_email' => 'jane@example.com',
                'details' => [['product_name' => 'A', 'quantity' => 1, 'unit_price' => 10]],
            ], [$file]);
            $this->fail('Expected the DB failure to propagate.');
        } catch (\RuntimeException) {
            // expected
        }

        // Compensation: the just-uploaded file must not linger on the disk.
        $this->assertEmpty(Storage::disk(OrderAttachmentStorage::DISK)->allFiles());
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
