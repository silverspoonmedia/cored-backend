<?php

namespace Tests\Domain\FileHosting\Unit\Services;

use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\SignedDownloadService;
use App\Domain\FileHosting\Support\ObjectKeyResolver;
use App\Domain\Identity\Models\User;
use Database\Seeders\FileHostingRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Real SignedDownloadService — proxy HMAC mint, cache host remint, direct fallback.
 */
class SignedDownloadServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private SignedDownloadService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(FileHostingRoleSeeder::class);

        config([
            'file-hosting.directory' => 'file-hosting',
            'file-hosting.signed_download_ttl_minutes' => 15,
            'file-hosting.signed_download_cache_margin_seconds' => 60,
            'file-hosting.upload_proxy.base_url' => '',
            'file-hosting.upload_proxy.token' => '',
            'file-hosting.upload_proxy.disk_targets' => [],
        ]);

        $this->service = app(SignedDownloadService::class);
    }

    public function test_proxy_mints_hmac_worker_url_without_raw_token(): void
    {
        config([
            'file-hosting.upload_proxy.base_url' => 'https://s3.silverspoon.me/',
            'file-hosting.upload_proxy.token' => 'strong-upload-token-16',
            'file-hosting.upload_proxy.disk_targets' => [
                's3FileHostingCe' => 'sbs',
            ],
        ]);

        Carbon::setTestNow(Carbon::parse('2026-07-24 10:00:00', 'UTC'));

        $user = $this->userWithRole('User');
        $file = $this->makeFile($user, [
            'disk' => 's3FileHostingCe',
            'name' => 'report.pdf',
            'mime' => 'application/pdf',
        ]);

        $resolved = $this->service->mintAndCache($file);
        $url = $resolved['url'];
        $expiresAt = $resolved['expires_at'];

        $objectKey = app(ObjectKeyResolver::class)->objectKey($user->id, $file->path);
        $exp = (string) $expiresAt->getTimestamp();
        $disposition = 'attachment; filename="report.pdf"; filename*=UTF-8\'\'report.pdf';

        $this->assertStringStartsWith('https://s3.silverspoon.me/t/sbs/', $url);
        $this->assertStringContainsString('/t/sbs/file-hosting/'.$user->id.'/'.$file->path.'?', $url);
        $this->assertStringNotContainsString('strong-upload-token-16', $url);

        $parts = parse_url($url);
        $this->assertIsArray($parts);
        parse_str($parts['query'] ?? '', $query);

        $this->assertSame($exp, $query['exp'] ?? null);
        $this->assertSame($disposition, $query['disp'] ?? null);
        $this->assertSame('application/pdf', $query['ctype'] ?? null);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) ($query['sig'] ?? ''));

        $payload = $this->service->downloadSignaturePayload(
            'sbs',
            $objectKey,
            $exp,
            $disposition,
            'application/pdf',
        );
        $expectedSig = hash_hmac('sha256', $payload, 'strong-upload-token-16');
        $this->assertSame($expectedSig, $query['sig']);

        $file->refresh();
        $this->assertSame($url, $file->signed_download_url);
        $this->assertTrue($expiresAt->equalTo($file->signed_download_expires_at));

        Carbon::setTestNow();
    }

    public function test_resolve_reuses_proxy_cache_when_host_matches(): void
    {
        config([
            'file-hosting.upload_proxy.base_url' => 'https://s3.silverspoon.me',
            'file-hosting.upload_proxy.token' => 'strong-upload-token-16',
            'file-hosting.upload_proxy.disk_targets' => [
                's3FileHostingCe' => 'sbs',
            ],
        ]);

        $user = $this->userWithRole('User');
        $file = $this->makeFile($user, ['disk' => 's3FileHostingCe']);

        $first = $this->service->mintAndCache($file);
        $second = $this->service->resolve($file->fresh());

        $this->assertSame($first['url'], $second['url']);
    }

    public function test_resolve_remints_when_cache_is_direct_b2_but_proxy_on(): void
    {
        config([
            'file-hosting.upload_proxy.base_url' => 'https://s3.silverspoon.me',
            'file-hosting.upload_proxy.token' => 'strong-upload-token-16',
            'file-hosting.upload_proxy.disk_targets' => [
                's3FileHostingCe' => 'sbs',
            ],
        ]);

        $user = $this->userWithRole('User');
        $file = $this->makeFile($user, [
            'disk' => 's3FileHostingCe',
            'signed_download_url' => 'https://s3.eu-central-003.backblazeb2.com/bucket/file?X-Amz-Signature=stale',
            'signed_download_expires_at' => now()->addHour(),
        ]);

        $resolved = $this->service->resolve($file);

        $this->assertStringStartsWith('https://s3.silverspoon.me/t/sbs/', $resolved['url']);
        $this->assertStringNotContainsString('backblazeb2.com', $resolved['url']);
        $this->assertSame($resolved['url'], $file->fresh()->signed_download_url);
    }

    public function test_resolve_remints_when_cache_is_worker_but_proxy_off(): void
    {
        // base_url still configured (stale Worker URLs detectable) but token empty → proxy off.
        config([
            'file-hosting.upload_proxy.base_url' => 'https://s3.silverspoon.me',
            'file-hosting.upload_proxy.token' => '',
            'file-hosting.upload_proxy.disk_targets' => [
                's3FileHostingCe' => 'sbs',
            ],
        ]);

        $user = $this->userWithRole('User');
        $stale = 'https://s3.silverspoon.me/t/sbs/file-hosting/x/y?exp=1&sig=ab';
        $file = $this->makeFile($user, [
            'disk' => 's3FileHostingCe',
            'signed_download_url' => $stale,
            'signed_download_expires_at' => now()->addHour(),
        ]);

        // Mode mismatch forces remint. Without S3 credentials this fails closed —
        // either way the stale Worker URL must not be reused.
        try {
            $resolved = $this->service->resolve($file);
            $this->assertNotSame($stale, $resolved['url']);
            $this->assertStringNotContainsString('s3.silverspoon.me', $resolved['url']);
        } catch (\App\Domain\FileHosting\Exceptions\FileHostingException $e) {
            $this->assertNotEmpty($e->getMessage());
            $this->assertSame($stale, $file->fresh()->signed_download_url);
        }
    }

    public function test_proxy_disabled_when_disk_alias_missing(): void
    {
        config([
            'file-hosting.upload_proxy.base_url' => 'https://s3.silverspoon.me',
            'file-hosting.upload_proxy.token' => 'strong-upload-token-16',
            'file-hosting.upload_proxy.disk_targets' => [
                's3FileHostingCe' => 'sbs',
            ],
        ]);

        $user = $this->userWithRole('User');
        // disk has no alias → must not build /t/{alias}/ Worker URL
        $file = $this->makeFile($user, ['disk' => 'local']);

        try {
            $url = $this->service->mintAndCache($file)['url'];
            $this->assertStringNotContainsString('/t/sbs/', $url);
            $this->assertStringNotContainsString('s3.silverspoon.me', $url);
        } catch (\App\Domain\FileHosting\Exceptions\FileHostingException $e) {
            // local/S3 temporaryUrl unavailable is fine — proxy path must not have run
            $this->assertNotEmpty($e->getMessage());
            $this->assertNull($file->fresh()->signed_download_url);
        }
    }

    public function test_legacy_s3custom_disk_maps_to_proxy_alias(): void
    {
        config([
            'file-hosting.upload_proxy.base_url' => 'https://proxy.example.workers.dev',
            'file-hosting.upload_proxy.token' => 'strong-upload-token-16',
            'file-hosting.upload_proxy.disk_targets' => [
                's3Custom' => 'sbs',
                's3FileHostingCe' => 'sbs',
            ],
        ]);

        $user = $this->userWithRole('User');
        $file = $this->makeFile($user, ['disk' => 's3Custom']);

        $url = $this->service->mintAndCache($file)['url'];

        $this->assertStringStartsWith('https://proxy.example.workers.dev/t/sbs/', $url);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeFile(User $user, array $overrides = []): HostedFile
    {
        return HostedFile::query()->create(array_merge([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'file.bin',
            'disk' => 'local',
            'path' => Str::lower(Str::random(ObjectKeyResolver::OBJECT_ID_LENGTH)),
            'size' => 1024,
            'mime' => 'application/octet-stream',
        ], $overrides));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
