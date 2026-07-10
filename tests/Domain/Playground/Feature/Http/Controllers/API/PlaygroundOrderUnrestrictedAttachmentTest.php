<?php

namespace Tests\Domain\Playground\Feature\Http\Controllers\API;

use App\Domain\Playground\Support\Orders\DirectAttachmentUploadService;
use App\Domain\Playground\Support\Orders\OrderAttachmentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeDirectAttachmentUploadService;
use Tests\TestCase;

/**
 * Order create/update via PlaygroundOrderController accepts staged uploads of any extension.
 */
class PlaygroundOrderUnrestrictedAttachmentTest extends TestCase
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

    public function test_store_accepts_staged_executable_attachment(): void
    {
        $service = app(DirectAttachmentUploadService::class);
        $uploadId = $service->complete(
            $service->initiate('installer.exe', 'application/octet-stream', 512)['upload']->id,
        )['upload']->id;

        $response = $this->postJson('/api/playground/orders', [
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'discount' => 0,
            'tax' => 0,
            'details' => [
                ['product_name' => 'Widget', 'quantity' => 1, 'unit_price' => 100, 'discount' => 0],
            ],
            'staged_attachments' => [$uploadId],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.attachment.0.name', 'installer.exe');
    }

    public function test_update_appends_staged_attachment_with_any_extension(): void
    {
        $service = app(DirectAttachmentUploadService::class);

        $created = $this->postJson('/api/playground/orders', [
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'discount' => 0,
            'tax' => 0,
            'details' => [
                ['product_name' => 'Widget', 'quantity' => 1, 'unit_price' => 100, 'discount' => 0],
            ],
        ])->json('data');

        $newUploadId = $service->complete(
            $service->initiate('notes.txt', 'text/plain', 128)['upload']->id,
        )['upload']->id;

        $response = $this->putJson('/api/playground/orders/'.$created['id'], [
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'discount' => 0,
            'tax' => 0,
            'details' => [
                ['product_name' => 'Widget', 'quantity' => 1, 'unit_price' => 100, 'discount' => 0],
            ],
            'staged_attachments' => [$newUploadId],
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data.attachment')
            ->assertJsonPath('data.attachment.0.name', 'notes.txt');
    }
}
