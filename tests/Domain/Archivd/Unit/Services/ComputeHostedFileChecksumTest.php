<?php

namespace Tests\Domain\Archivd\Unit\Services;

use App\Domain\Archivd\Jobs\ComputeHostedFileChecksum;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Support\ObjectKeyResolver;
use App\Domain\Archivd\Support\ObjectStorage;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ComputeHostedFileChecksumTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['archivd.disk' => 'local']);
        Storage::fake('local');
    }

    public function test_it_streams_the_object_and_stores_its_sha256(): void
    {
        $user = User::factory()->create();
        $bytes = random_bytes(256 * 1024); // 256 KB

        $file = $this->makeFile($user);

        $objectKey = app(ObjectKeyResolver::class)->objectKey($user->id, $file->path);
        Storage::disk('local')->put($objectKey, $bytes);

        (new ComputeHostedFileChecksum($file->id))
            ->handle(new ObjectStorage, new ObjectKeyResolver);

        $this->assertSame(hash('sha256', $bytes), $file->fresh()->checksum);
    }

    public function test_it_is_idempotent_and_skips_an_existing_checksum(): void
    {
        $user = User::factory()->create();

        $file = $this->makeFile($user);
        $existing = hash('sha256', 'already-set');
        $file->forceFill(['checksum' => $existing])->save();

        // No object on storage at all — must still no-op, not overwrite or throw.
        (new ComputeHostedFileChecksum($file->id))
            ->handle(new ObjectStorage, new ObjectKeyResolver);

        $this->assertSame($existing, $file->fresh()->checksum);
    }

    public function test_it_ignores_a_missing_file(): void
    {
        (new ComputeHostedFileChecksum(Str::uuid()->toString()))
            ->handle(new ObjectStorage, new ObjectKeyResolver);

        $this->assertTrue(true); // No exception = pass.
    }

    public function test_it_leaves_checksum_null_when_object_is_missing(): void
    {
        $user = User::factory()->create();
        $file = $this->makeFile($user);

        // Object never landed on storage.
        (new ComputeHostedFileChecksum($file->id))
            ->handle(new ObjectStorage, new ObjectKeyResolver);

        $this->assertNull($file->fresh()->checksum);
    }

    private function makeFile(User $user): HostedFile
    {
        return HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'blob.bin',
            'disk' => 'local',
            'path' => Str::lower(Str::random(40)),
            'size' => 10,
            'mime' => 'application/octet-stream',
        ]);
    }
}
