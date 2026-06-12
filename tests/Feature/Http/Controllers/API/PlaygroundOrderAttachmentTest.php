<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Support\Orders\OrderAttachmentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlaygroundOrderAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(OrderAttachmentStorage::DISK);
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

    public function test_store_uploads_files_and_persists_metadata(): void
    {
        $response = $this->postJson('/api/playground/orders', $this->basePayload([
            'attachments' => [
                UploadedFile::fake()->create('invoice.pdf', 120, 'application/pdf'),
                UploadedFile::fake()->image('photo.png'),
            ],
        ]));

        $response->assertCreated()
            ->assertJsonCount(2, 'data.attachment')
            ->assertJsonPath('data.attachment.0.name', 'invoice.pdf');

        $disk = Storage::disk(OrderAttachmentStorage::DISK);
        $this->assertCount(2, $disk->allFiles());

        // Each stored path actually exists on the disk + carries a url.
        $paths = collect($response->json('data.attachment'))->pluck('path');
        foreach ($paths as $path) {
            $disk->assertExists($path);
        }
        $this->assertNotNull($response->json('data.attachment.0.url'));
    }

    public function test_store_rejects_disallowed_file_type(): void
    {
        $response = $this->postJson('/api/playground/orders', $this->basePayload([
            'attachments' => [UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream')],
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['attachments.0']);

        $this->assertEmpty(Storage::disk(OrderAttachmentStorage::DISK)->allFiles());
    }

    public function test_update_keeps_selected_files_and_removes_the_rest(): void
    {
        $created = $this->postJson('/api/playground/orders', $this->basePayload([
            'attachments' => [
                UploadedFile::fake()->create('keep.pdf', 50, 'application/pdf'),
                UploadedFile::fake()->create('drop.pdf', 50, 'application/pdf'),
            ],
        ]))->json('data');

        $keepPath = collect($created['attachment'])->firstWhere('name', 'keep.pdf')['path'];
        $dropPath = collect($created['attachment'])->firstWhere('name', 'drop.pdf')['path'];

        $disk = Storage::disk(OrderAttachmentStorage::DISK);
        $disk->assertExists($keepPath);
        $disk->assertExists($dropPath);

        $response = $this->putJson('/api/playground/orders/'.$created['id'], $this->basePayload([
            'keep_attachments' => [$keepPath],
            'attachments' => [UploadedFile::fake()->create('new.pdf', 50, 'application/pdf')],
        ]));

        $response->assertOk()
            ->assertJsonCount(2, 'data.attachment');

        // Kept + newly uploaded survive; the dropped file is deleted from disk.
        $disk->assertExists($keepPath);
        $disk->assertMissing($dropPath);

        $names = collect($response->json('data.attachment'))->pluck('name')->all();
        $this->assertContains('keep.pdf', $names);
        $this->assertContains('new.pdf', $names);
        $this->assertNotContains('drop.pdf', $names);
    }

    public function test_update_without_keep_list_preserves_existing_files(): void
    {
        $created = $this->postJson('/api/playground/orders', $this->basePayload([
            'attachments' => [UploadedFile::fake()->create('keep.pdf', 50, 'application/pdf')],
        ]))->json('data');

        // No `keep_attachments` key, no new files → metadata-only edit keeps all.
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
        $created = $this->postJson('/api/playground/orders', $this->basePayload([
            'attachments' => [UploadedFile::fake()->create('gone.pdf', 50, 'application/pdf')],
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
        // Multipart cannot encode an empty `keep_attachments` array, so clients
        // signal "I manage attachments" with attachments_managed=1; an absent
        // keep-list then means "keep none", not "keep all".
        $created = $this->postJson('/api/playground/orders', $this->basePayload([
            'attachments' => [UploadedFile::fake()->create('gone.pdf', 50, 'application/pdf')],
        ]))->json('data');
        $path = $created['attachment'][0]['path'];

        $response = $this->putJson('/api/playground/orders/'.$created['id'], $this->basePayload([
            'attachments_managed' => '1',
        ]));

        $response->assertOk()
            ->assertJsonCount(0, 'data.attachment');

        Storage::disk(OrderAttachmentStorage::DISK)->assertMissing($path);
    }

    public function test_update_managed_marker_keeps_listed_files(): void
    {
        $created = $this->postJson('/api/playground/orders', $this->basePayload([
            'attachments' => [
                UploadedFile::fake()->create('keep.pdf', 50, 'application/pdf'),
                UploadedFile::fake()->create('drop.pdf', 50, 'application/pdf'),
            ],
        ]))->json('data');

        $keepPath = collect($created['attachment'])->firstWhere('name', 'keep.pdf')['path'];
        $dropPath = collect($created['attachment'])->firstWhere('name', 'drop.pdf')['path'];

        // Marker present AND a keep-list present: the explicit list wins.
        $response = $this->putJson('/api/playground/orders/'.$created['id'], $this->basePayload([
            'attachments_managed' => '1',
            'keep_attachments' => [$keepPath],
        ]));

        $response->assertOk()->assertJsonCount(1, 'data.attachment');

        $disk = Storage::disk(OrderAttachmentStorage::DISK);
        $disk->assertExists($keepPath);
        $disk->assertMissing($dropPath);
    }

    public function test_destroy_removes_attachment_files(): void
    {
        $order = Order::factory()->pending()->create([
            'attachment' => [
                ['path' => 'orders/attachments/x.pdf', 'name' => 'x.pdf', 'size' => 10, 'mime' => 'application/pdf'],
            ],
        ]);
        OrderDetail::factory()->create(['order_master_id' => $order->id]);

        $disk = Storage::disk(OrderAttachmentStorage::DISK);
        $disk->put('orders/attachments/x.pdf', 'dummy');

        $this->deleteJson('/api/playground/orders/'.$order->id)->assertOk();

        $disk->assertMissing('orders/attachments/x.pdf');
        $this->assertDatabaseCount('order_master', 0);
    }
}
