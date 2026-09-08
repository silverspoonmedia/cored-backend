<?php

namespace Tests\Domain\Archivd\Unit\Support;

use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Support\RegionResolver;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'archivd.regions' => [
                'ce' => 's3ArchivdCe',
            ],
            'archivd.disk_aliases' => [],
            'archivd.disabled_regions' => [],
        ]);
    }

    public function test_key_for_disk_maps_primary_ce_disk(): void
    {
        $this->assertSame('ce', RegionResolver::keyForDisk('s3ArchivdCe'));
    }

    public function test_key_for_disk_returns_null_for_unknown_disk(): void
    {
        $this->assertNull(RegionResolver::keyForDisk('not-a-disk'));
    }

    public function test_uploadable_keys_exclude_disabled(): void
    {
        config([
            'archivd.regions' => [
                'ce' => 's3ArchivdCe',
                'us' => 's3ArchivdUs',
            ],
            'archivd.disabled_regions' => ['us'],
        ]);

        $this->assertSame(['ce'], RegionResolver::uploadableKeys());
        $this->assertTrue(RegionResolver::isDisabled('us'));
        $this->assertFalse(RegionResolver::isDisabled('ce'));
    }

    public function test_should_display_region_false_for_single_configured_region_without_history(): void
    {
        $this->assertFalse(RegionResolver::shouldDisplayRegion());
    }

    public function test_should_display_region_true_when_multiple_regions_configured(): void
    {
        config([
            'archivd.regions' => [
                'ce' => 's3ArchivdCe',
                'us' => 's3ArchivdUs',
            ],
        ]);

        $this->assertTrue(RegionResolver::shouldDisplayRegion());
    }

    public function test_should_display_region_true_when_history_has_two_effective_disks(): void
    {
        config([
            'archivd.regions' => [
                'ce' => 's3ArchivdCe',
            ],
        ]);

        $user = User::factory()->create();

        HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'ce.pdf',
            'disk' => 's3ArchivdCe',
            'path' => 'ce-path-1',
            'size' => 10,
            'mime' => 'application/pdf',
        ]);
        HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'us.pdf',
            'disk' => 's3ArchivdUs',
            'path' => 'us-path-1',
            'size' => 10,
            'mime' => 'application/pdf',
        ]);

        $this->assertSame(2, RegionResolver::historicalEffectiveDiskCount());
        $this->assertTrue(RegionResolver::shouldDisplayRegion());
    }

    public function test_configured_keys_lists_region_keys(): void
    {
        config([
            'archivd.regions' => [
                'ce' => 's3ArchivdCe',
                'us' => 's3ArchivdUs',
            ],
        ]);

        $this->assertSame(['ce', 'us'], RegionResolver::configuredKeys());
    }
}
