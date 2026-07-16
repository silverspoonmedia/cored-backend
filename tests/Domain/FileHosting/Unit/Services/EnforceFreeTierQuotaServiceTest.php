<?php

namespace Tests\Domain\FileHosting\Unit\Services;

use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\EnforceFreeTierQuotaService;
use App\Domain\FileHosting\Support\ObjectStorage;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class EnforceFreeTierQuotaServiceTest extends TestCase
{
    use RefreshDatabase;

    private const GIB = 1024 * 1024 * 1024;

    protected function setUp(): void
    {
        parent::setUp();
        config(['file-hosting.default_quota_bytes' => 5 * self::GIB]);
    }

    public function test_keeps_oldest_until_free_limit_deletes_overflow(): void
    {
        $user = User::factory()->create();

        $a = $this->file($user->id, 2 * self::GIB, '2026-01-01 00:00:00', 'a.bin');
        $b = $this->file($user->id, 3 * self::GIB, '2026-01-03 00:00:00', 'b.bin');
        $c = $this->file($user->id, 5 * self::GIB, '2026-01-03 01:00:00', 'c.bin');

        $storage = Mockery::mock(ObjectStorage::class);
        $storage->shouldReceive('deleteMany')
            ->once()
            ->withArgs(function (array $objects) use ($c): bool {
                return count($objects) === 1
                    && ($objects[0]['path'] ?? null) === $c->path;
            });

        $this->app->instance(ObjectStorage::class, $storage);

        $result = app(EnforceFreeTierQuotaService::class)->enforce($user->id);

        $this->assertSame(1, $result['deleted']);
        $this->assertSame(5 * self::GIB, $result['freed_bytes']);
        $this->assertSame(5 * self::GIB, $result['retained_bytes']);

        $this->assertNotNull(HostedFile::query()->find($a->id));
        $this->assertNotNull(HostedFile::query()->find($b->id));
        $this->assertNull(HostedFile::query()->find($c->id));
        $this->assertNotNull(HostedFile::onlyTrashed()->find($c->id));
    }

    public function test_under_quota_is_noop(): void
    {
        $user = User::factory()->create();
        $this->file($user->id, 1 * self::GIB, '2026-01-01 00:00:00', 'small.bin');

        $storage = Mockery::mock(ObjectStorage::class);
        $storage->shouldNotReceive('deleteMany');
        $this->app->instance(ObjectStorage::class, $storage);

        $result = app(EnforceFreeTierQuotaService::class)->enforce($user->id);

        $this->assertSame(0, $result['deleted']);
        $this->assertSame(1, HostedFile::query()->where('user_id', $user->id)->count());
    }

    private function file(string $userId, int $size, string $createdAt, string $name): HostedFile
    {
        Carbon::setTestNow($createdAt);

        $file = HostedFile::query()->create([
            'user_id' => $userId,
            'folder_id' => null,
            'name' => $name,
            'disk' => 's3Custom',
            'path' => 'file-hosting/'.$userId.'/'.$name,
            'size' => $size,
            'mime' => 'application/octet-stream',
            'visibility' => 'private',
        ]);

        Carbon::setTestNow();

        return $file;
    }
}
