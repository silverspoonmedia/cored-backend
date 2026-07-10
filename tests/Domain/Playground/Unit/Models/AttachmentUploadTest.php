<?php

namespace Tests\Domain\Playground\Unit\Models;

use App\Domain\Playground\Models\AttachmentUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_multipart_upload_id_longer_than_255_characters_persists(): void
    {
        $longUploadId = str_repeat('a', 300).'/'.str_repeat('b', 100);

        $upload = AttachmentUpload::factory()->multipart()->create([
            's3_upload_id' => $longUploadId,
        ]);

        $this->assertDatabaseHas('attachment_uploads', [
            'id' => $upload->id,
            's3_upload_id' => $longUploadId,
        ]);

        $this->assertSame($longUploadId, $upload->fresh()->s3_upload_id);
    }
}
