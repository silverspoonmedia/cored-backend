<?php

namespace Tests\Domain\FileHosting\Unit\Services;

use App\Domain\FileHosting\Models\FileUpload;
use App\Domain\FileHosting\Services\DirectUploadService;
use App\Domain\Identity\Models\User;
use App\Support\Storage\S3DirectUploadEngine;
use Database\Seeders\FileHostingRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class DirectUploadServiceResumeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private FakeResumeEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(FileHostingRoleSeeder::class);

        config([
            'file-hosting.disk' => 'local',
            'file-hosting.directory' => 'file-hosting',
            'file-hosting.default_quota_bytes' => 500 * 1024 * 1024,
            'file-hosting.allowed_mime_prefixes' => ['application/'],
            'file-hosting.chunk_size_bytes' => 10 * 1024 * 1024,
            'file-hosting.multipart_threshold_bytes' => 10 * 1024 * 1024,
            'file-hosting.max_concurrent_uploads' => 0,
            'file-hosting.upload_proxy.base_url' => '',
            'file-hosting.upload_proxy.token' => '',
            'file-hosting.upload_proxy.disk_targets' => [],
        ]);

        Storage::fake('local');

        // Bind a fake engine so no real S3 CreateMultipartUpload / ListParts / presign runs.
        $this->engine = new FakeResumeEngine;
        $this->app->instance(S3DirectUploadEngine::class, $this->engine);
    }

    public function test_initiate_resumes_existing_pending_session_instead_of_creating_a_new_one(): void
    {
        $user = $this->userWithRole('User');
        $hash = str_repeat('a', 64);
        $size = 25 * 1024 * 1024; // 3 parts at 10 MB chunk.

        $existing = $this->pendingMultipart($user, $hash, $size);

        // S3 already holds part 1.
        $this->engine->partsToReturn = [
            ['part_number' => 1, 'etag' => 'etag-1', 'size' => 10 * 1024 * 1024],
        ];

        $result = app(DirectUploadService::class)->initiate(
            $user,
            'movie.bin',
            'application/octet-stream',
            $size,
            null,
            null,
            null,
            null,
            $hash,
        );

        $this->assertTrue($result['resumed']);
        $this->assertSame($existing->id, $result['upload']->id);
        $this->assertSame(1, FileUpload::query()->count(), 'No new upload row should be created on resume');
        $this->assertCount(1, $result['recorded_parts']);
        $this->assertSame(1, $result['recorded_parts'][0]['part_number']);
        // Only the 2 missing parts get presigned.
        $this->assertSame([2, 3], array_column($result['parts'], 'part_number'));
    }

    public function test_initiate_creates_new_session_when_hash_does_not_match(): void
    {
        $user = $this->userWithRole('User');
        $size = 25 * 1024 * 1024;

        $this->pendingMultipart($user, str_repeat('a', 64), $size);

        $result = app(DirectUploadService::class)->initiate(
            $user,
            'other.bin',
            'application/octet-stream',
            $size,
            null,
            null,
            null,
            null,
            str_repeat('b', 64),
        );

        $this->assertFalse($result['resumed']);
        $this->assertSame(2, FileUpload::query()->count());
        $this->assertSame($size, (int) $result['upload']->size);
        // Chunk layout is frozen on the new row.
        $this->assertSame(10 * 1024 * 1024, (int) $result['upload']->chunk_size);
        $this->assertSame(3, (int) $result['upload']->part_count);
    }

    public function test_record_parts_merges_into_the_parts_column(): void
    {
        $user = $this->userWithRole('User');
        $upload = $this->pendingMultipart($user, str_repeat('c', 64), 25 * 1024 * 1024);

        app(DirectUploadService::class)->recordParts($user, $upload->id, [
            ['part_number' => 2, 'etag' => 'e2', 'size' => 5],
        ]);
        $out = app(DirectUploadService::class)->recordParts($user, $upload->id, [
            ['part_number' => 1, 'etag' => 'e1', 'size' => 5],
        ]);

        // Sorted, both recorded.
        $this->assertSame([1, 2], array_column($out['recorded_parts'], 'part_number'));
        $this->assertSame(2, count($upload->refresh()->recordedParts()));
    }

    public function test_status_reconciles_recorded_parts_from_s3(): void
    {
        $user = $this->userWithRole('User');
        $upload = $this->pendingMultipart($user, str_repeat('d', 64), 25 * 1024 * 1024);

        // DB claims parts 1 and 2, but S3 only actually has part 1.
        $upload->forceFill(['parts' => [
            ['part_number' => 1, 'etag' => 'e1', 'size' => 10],
            ['part_number' => 2, 'etag' => 'e2', 'size' => 10],
        ]])->save();

        $this->engine->partsToReturn = [
            ['part_number' => 1, 'etag' => 'e1', 'size' => 10],
        ];

        $result = app(DirectUploadService::class)->status($user, $upload->id);

        $this->assertSame([1], array_column($result['recorded_parts'], 'part_number'));
        // Persisted back to the row.
        $this->assertSame([1], array_column($upload->refresh()->recordedParts(), 'part_number'));
        // Missing parts 2 and 3 presigned.
        $this->assertSame([2, 3], array_column($result['parts'], 'part_number'));
    }

    private function pendingMultipart(User $user, string $hash, int $size): FileUpload
    {
        return FileUpload::query()->create([
            'user_id' => $user->id,
            'workspace_id' => null,
            'folder_id' => null,
            'disk' => 'local',
            'path' => Str::lower(Str::random(40)),
            's3_upload_id' => 's3-upload-id',
            'strategy' => FileUpload::STRATEGY_MULTIPART,
            'original_name' => 'movie.bin',
            'mime' => 'application/octet-stream',
            'size' => $size,
            'content_hash' => $hash,
            'chunk_size' => 10 * 1024 * 1024,
            'part_count' => 3,
            'status' => FileUpload::STATUS_PENDING,
            'parts' => [],
            'expires_at' => Carbon::now()->addHour(),
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}

/**
 * Real engine with S3 network calls stubbed for resume tests.
 */
class FakeResumeEngine extends S3DirectUploadEngine
{
    /** @var list<array{part_number: int, etag: string, size: int}> */
    public array $partsToReturn = [];

    public function createMultipartUpload(string $diskName, string $path, string $mime): string
    {
        return 'fake-s3-upload-id';
    }

    public function listParts(string $diskName, string $path, string $s3UploadId): array
    {
        return $this->partsToReturn;
    }

    public function presignParts(
        string $diskName,
        string $path,
        string $strategy,
        string $mime,
        ?string $s3UploadId,
        array $partNumbers,
        int $presignTtlMinutes,
    ): array {
        return array_map(static fn (int $n): array => [
            'part_number' => $n,
            'url' => 'https://storage.test/part/'.$n,
            'headers' => [],
        ], $partNumbers);
    }
}
