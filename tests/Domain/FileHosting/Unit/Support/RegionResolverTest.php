<?php

namespace Tests\Domain\FileHosting\Unit\Support;

use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Support\RegionResolver;
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
            'file-hosting.regions' => [
                'ce' => 's3FileHostingCe',
            ],
            'file-hosting.disk_aliases' => [
                's3Custom' => 's3FileHostingCe',
            ],
            'file-hosting.disabled_regions' => [],
        ]);
    }

    public function test_key_for_disk_maps_primary_ce_disk(): void
    {
        $this->assertSame('ce', RegionResolver::keyForDisk('s3FileHostingCe'));
    }

    public function test_key_for_disk_resolves_legacy_s3_custom_alias_to_ce(): void
    {
        $this->assertSame('ce', RegionResolver::keyForDisk('s3Custom'));
        $this->assertSame('s3FileHostingCe', RegionResolver::canonicalDisk('s3Custom'));
    }

    public function test_key_for_disk_returns_null_for_unknown_disk(): void
    {
        $this->assertNull(RegionResolver::keyForDisk('not-a-disk'));
    }

    public function test_uploadable_keys_exclude_disabled(): void
    {
        config([
            'file-hosting.regions' => [
                'ce' => 's3FileHostingCe',
                'us' => 's3FileHostingUs',
            ],
            'file-hosting.disabled_regions' => ['us'],
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
            'file-hosting.regions' => [
                'ce' => 's3FileHostingCe',
                'us' => 's3FileHostingUs',
            ],
        ]);

        $this->assertTrue(RegionResolver::shouldDisplayRegion());
    }

    public function test_should_display_region_false_when_history_only_has_aliased_same_target(): void
    {
        $user = User::factory()->create();

        HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'legacy.pdf',
            'disk' => 's3Custom',
            'path' => 'legacy-path-1',
            'size' => 10,
            'mime' => 'application/pdf',
        ]);
        HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'new.pdf',
            'disk' => 's3FileHostingCe',
            'path' => 'new-path-1',
            'size' => 10,
            'mime' => 'application/pdf',
        ]);

        $this->assertSame(1, RegionResolver::historicalEffectiveDiskCount());
        $this->assertFalse(RegionResolver::shouldDisplayRegion());
    }

    public function test_should_display_region_true_when_history_has_two_effective_disks(): void
    {
        config([
            'file-hosting.regions' => [
                'ce' => 's3FileHostingCe',
            ],
        ]);

        $user = User::factory()->create();

        HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'ce.pdf',
            'disk' => 's3FileHostingCe',
            'path' => 'ce-path-1',
            'size' => 10,
            'mime' => 'application/pdf',
        ]);
        HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'us.pdf',
            'disk' => 's3FileHostingUs',
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
            'file-hosting.regions' => [
                'ce' => 's3FileHostingCe',
                'eu' => 's3FileHostingEu',
            ],
        ]);

        $this->assertSame(['ce', 'eu'], RegionResolver::configuredKeys());
    }
}
