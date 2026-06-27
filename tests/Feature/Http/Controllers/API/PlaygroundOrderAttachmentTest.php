<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Models\AttachmentUpload;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Support\Orders\DirectAttachmentUploadService;
use App\Support\Orders\OrderAttachmentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeDirectAttachmentUploadService;
use Tests\TestCase;

class PlaygroundOrderAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(OrderAttachmentStorage::DISK);
        $this->app->instance(
            DirectAttachmentUploadService::class,
            new FakeDirectAttachmentUploadService(new OrderAttachmentStorage),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'discount' => 0,
            'tax' => 0,
            'details' => [
                ['product_name' => 'Widget', 'quantity' => 1, 'unit_price' => 100, 'discount' => 0],
            ],
        ], $overrides);
    }

    private function stageUpload(string $name = 'invoice.pdf', int $size = 1024): string
    {
        $service = app(DirectAttachmentUploadService::class);
        $init = $service->initiate($name, 'application/pdf', $size);

        return $service->complete($init['upload']->id)['upload']->id;
    }

    public function test_store_persists_staged_attachment_metadata(): void
    {
        $first = $this->stageUpload('invoice.pdf');
        $second = $this->stageUpload('photo.png', 2048);

        $response = $this->postJson('/api/playground/orders', $this->basePayload([
            'staged_attachments' => [$first, $second],
        ]));

        $response->assertCreated()
            ->assertJsonCount(2, 'data.attachment')
            ->assertJsonPath('data.attachment.0.name', 'invoice.pdf');

        $disk = Storage::disk(OrderAttachmentStorage::DISK);
        $paths = collect($response->json('data.attachment'))->pluck('path');

        foreach ($paths as $path) {
            $disk->assertExists($path);
        }

        $this->assertNotNull($response->json('data.attachment.0.url'));
    }

    public function test_store_rejects_incomplete_staged_upload(): void
    {
        $pending = AttachmentUpload::factory()->create([
            'status' => AttachmentUpload::STATUS_PENDING,
        ]);

        $response = $this->postJson('/api/playground/orders', $this->basePayload([
            'staged_attachments' => [$pending->id],
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['staged_attachments.0']);
    }

    public function test_update_keeps_selected_files_and_removes_the_rest(): void
    {
        $keepId = $this->stageUpload('keep.pdf');
        $dropId = $this->stageUpload('drop.pdf');
        $newId = $this->stageUpload('new.pdf');

        $created = $this->postJson('/api/playground/orders', $this->basePayload([
            'staged_attachments' => [$keepId, $dropId],
        ]))->json('data');

        $keepPath = collect($created['attachment'])->firstWhere('name', 'keep.pdf')['path'];
        $dropPath = collect($created['attachment'])->firstWhere('name', 'drop.pdf')['path'];

        $disk = Storage::disk(OrderAttachmentStorage::DISK);
        $disk->assertExists($keepPath);
        $disk->assertExists($dropPath);

        $response = $this->putJson('/api/playground/orders/'.$created['id'], $this->basePayload([
            'keep_attachments' => [$keepPath],
            'staged_attachments' => [$newId],
        ]));

        $response->assertOk()
            ->assertJsonCount(2, 'data.attachment');

        $disk->assertExists($keepPath);
        $disk->assertMissing($dropPath);

        $names = collect($response->json('data.attachment'))->pluck('name')->all();
        $this->assertContains('keep.pdf', $names);
        $this->assertContains('new.pdf', $names);
        $this->assertNotContains('drop.pdf', $names);
    }

    public function test_update_without_keep_list_preserves_existing_files(): void
    {
        $uploadId = $this->stageUpload('keep.pdf');

        $created = $this->postJson('/api/playground/orders', $this->basePayload([
            'staged_attachments' => [$uploadId],
        ]))->json('data');

        $response = $this->putJson('/api/playground/orders/'.$created['id'], $this->basePayload([
            'customer_name' => 'Renamed',
        ]));

        $response->assertOk()
            ->assertJsonPath('data.customer_name', 'Renamed')
            ->assertJsonCount(1, 'data.attachment');

        Storage::disk(OrderAttachmentStorage::DISK)
            ->assertExists($created['attachment'][0]['path']);
    }

    public function test_update_dropping_all_files_with_empty_keep_list(): void
    {
        $uploadId = $this->stageUpload('gone.pdf');

        $created = $this->postJson('/api/playground/orders', $this->basePayload([
            'staged_attachments' => [$uploadId],
        ]))->json('data');
        $path = $created['attachment'][0]['path'];

        $response = $this->putJson('/api/playground/orders/'.$created['id'], $this->basePayload([
            'keep_attachments' => [],
        ]));

        $response->assertOk()
            ->assertJsonCount(0, 'data.attachment');

        Storage::disk(OrderAttachmentStorage::DISK)->assertMissing($path);
    }

    public function test_update_dropping_all_files_via_managed_marker(): void
    {
        $uploadId = $this->stageUpload('gone.pdf');

        $created = $this->postJson('/api/playground/orders', $this->basePayload([
            'staged_attachments' => [$uploadId],
        ]))->json('data');
        $path = $created['attachment'][0]['path'];

        $response = $this->putJson('/api/playground/orders/'.$created['id'], $this->basePayload([
            'attachments_managed' => true,
        ]));

        $response->assertOk()
            ->assertJsonCount(0, 'data.attachment');

        Storage::disk(OrderAttachmentStorage::DISK)->assertMissing($path);
    }

    public function test_update_managed_marker_keeps_listed_files(): void
    {
        $keepId = $this->stageUpload('keep.pdf');
        $dropId = $this->stageUpload('drop.pdf');

        $created = $this->postJson('/api/playground/orders', $this->basePayload([
            'staged_attachments' => [$keepId, $dropId],
        ]))->json('data');

        $keepPath = collect($created['attachment'])->firstWhere('name', 'keep.pdf')['path'];
        $dropPath = collect($created['attachment'])->firstWhere('name', 'drop.pdf')['path'];

        $response = $this->putJson('/api/playground/orders/'.$created['id'], $this->basePayload([
            'attachments_managed' => true,
            'keep_attachments' => [$keepPath],
        ]));

        $response->assertOk()->assertJsonCount(1, 'data.attachment');

        $disk = Storage::disk(OrderAttachmentStorage::DISK);
        $disk->assertExists($keepPath);
        $disk->assertMissing($dropPath);
    }

    public function test_destroy_removes_attachment_files(): void
    {
        $order = Order::factory()->pending()->create();
        $order->attachments()->create(
            ['disk' => OrderAttachmentStorage::DISK, 'path' => 'orders/attachments/x.pdf', 'name' => 'x.pdf', 'size' => 10, 'mime' => 'application/pdf'],
        );
        OrderDetail::factory()->create(['order_master_id' => $order->id]);

        $disk = Storage::disk(OrderAttachmentStorage::DISK);
        $disk->put('orders/attachments/x.pdf', 'dummy');

        $this->deleteJson('/api/playground/orders/'.$order->id)->assertOk();

        $disk->assertMissing('orders/attachments/x.pdf');
        $this->assertDatabaseCount('order_master', 0);
        $this->assertDatabaseCount('attachments', 0);
    }
}
