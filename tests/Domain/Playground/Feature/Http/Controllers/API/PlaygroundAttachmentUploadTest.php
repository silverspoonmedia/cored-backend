<?php

namespace Tests\Domain\Playground\Feature\Http\Controllers\API;

use App\Domain\Playground\Support\Orders\DirectAttachmentUploadService;
use App\Domain\Playground\Support\Orders\OrderAttachmentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeDirectAttachmentUploadService;
use Tests\TestCase;

class PlaygroundAttachmentUploadTest extends TestCase
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

    public function test_init_creates_upload_session(): void
    {
        $response = $this->postJson('/api/playground/attachments/uploads', [
            'name' => 'invoice.pdf',
            'mime' => 'application/pdf',
            'size' => 4096,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.strategy', 'single')
            ->assertJsonPath('data.part_count', 1)
            ->assertJsonCount(1, 'data.parts');

        $this->assertDatabaseHas('attachment_uploads', [
            'original_name' => 'invoice.pdf',
            'status' => 'pending',
        ]);
    }

    public function test_init_accepts_any_file_extension(): void
    {
        $response = $this->postJson('/api/playground/attachments/uploads', [
            'name' => 'setup.exe',
            'mime' => 'application/octet-stream',
            'size' => 100,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('attachment_uploads', [
            'original_name' => 'setup.exe',
            'status' => 'pending',
        ]);
    }

    public function test_init_rejects_empty_file_name(): void
    {
        $response = $this->postJson('/api/playground/attachments/uploads', [
            'name' => '   ',
            'mime' => 'application/octet-stream',
            'size' => 100,
        ]);

        $response->assertStatus(422);
    }

    public function test_complete_marks_upload_ready_for_order_attachment(): void
    {
        $init = $this->postJson('/api/playground/attachments/uploads', [
            'name' => 'invoice.pdf',
            'mime' => 'application/pdf',
            'size' => 4096,
        ])->json('data');

        $uploadId = $init['upload_id'];

        $response = $this->postJson("/api/playground/attachments/uploads/{$uploadId}/complete", [
            'parts' => [['part_number' => 1, 'etag' => 'abc123']],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.metadata.name', 'invoice.pdf');

        $this->assertDatabaseHas('attachment_uploads', [
            'id' => $uploadId,
            'status' => 'completed',
        ]);

        Storage::disk(OrderAttachmentStorage::DISK)->assertExists($init['path']);
    }

    public function test_status_returns_resume_payload(): void
    {
        config([
            'attachments.multipart_threshold_bytes' => 1024,
            'attachments.chunk_size_bytes' => 1024,
        ]);

        $init = $this->postJson('/api/playground/attachments/uploads', [
            'name' => 'large.pdf',
            'mime' => 'application/pdf',
            'size' => 2048,
        ])->json('data');

        $response = $this->getJson('/api/playground/attachments/uploads/'.$init['upload_id']);

        $response->assertOk()
            ->assertJsonPath('data.strategy', 'multipart')
            ->assertJsonPath('data.part_count', 2)
            ->assertJsonStructure(['data' => ['recorded_parts', 'parts']]);
    }

    public function test_abort_marks_upload_aborted(): void
    {
        $init = $this->postJson('/api/playground/attachments/uploads', [
            'name' => 'invoice.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
        ])->json('data');

        $this->deleteJson('/api/playground/attachments/uploads/'.$init['upload_id'])
            ->assertOk();

        $this->assertDatabaseHas('attachment_uploads', [
            'id' => $init['upload_id'],
            'status' => 'aborted',
        ]);
    }
}
