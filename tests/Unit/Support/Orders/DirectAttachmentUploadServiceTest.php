<?php

namespace Tests\Unit\Support\Orders;

use App\Exceptions\Playground\AttachmentUploadException;
use App\Models\AttachmentUpload;
use App\Support\Orders\OrderAttachmentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeDirectAttachmentUploadService;
use Tests\TestCase;

class DirectAttachmentUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    private FakeDirectAttachmentUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(OrderAttachmentStorage::DISK);
        $this->service = new FakeDirectAttachmentUploadService(new OrderAttachmentStorage);
    }

    public function test_initiate_accepts_any_extension_and_mime(): void
    {
        $result = $this->service->initiate('clip.mp4', 'video/mp4', 1024);

        $this->assertSame('clip.mp4', $result['upload']->original_name);
        $this->assertSame('video/mp4', $result['upload']->mime);
    }

    public function test_initiate_rejects_empty_file_name(): void
    {
        $this->expectException(AttachmentUploadException::class);

        $this->service->initiate('   ', 'application/octet-stream', 1024);
    }

    public function test_initiate_uses_single_strategy_for_small_files(): void
    {
        config(['attachments.multipart_threshold_bytes' => 8192]);

        $result = $this->service->initiate('note.pdf', 'application/pdf', 1024);

        $this->assertSame(AttachmentUpload::STRATEGY_SINGLE, $result['strategy']);
        $this->assertSame(1, $result['part_count']);
    }

    public function test_initiate_uses_multipart_strategy_for_large_files(): void
    {
        config([
            'attachments.multipart_threshold_bytes' => 1024,
            'attachments.chunk_size_bytes' => 1024,
        ]);

        $result = $this->service->initiate('large.pdf', 'application/pdf', 3072);

        $this->assertSame(AttachmentUpload::STRATEGY_MULTIPART, $result['strategy']);
        $this->assertSame(3, $result['part_count']);
    }

    public function test_resolve_staged_requires_completed_upload(): void
    {
        $pending = AttachmentUpload::factory()->create();

        $this->expectException(AttachmentUploadException::class);

        $this->service->resolveStaged([$pending->id]);
    }

    public function test_resolve_staged_returns_metadata_for_completed_upload(): void
    {
        $init = $this->service->initiate('ready.pdf', 'application/pdf', 512);
        $completed = $this->service->complete($init['upload']->id);

        $resolved = $this->service->resolveStaged([$completed['upload']->id]);

        $this->assertSame('ready.pdf', $resolved[0]['name']);
        Storage::disk($resolved[0]['disk'])->assertExists($resolved[0]['path']);
    }
}
