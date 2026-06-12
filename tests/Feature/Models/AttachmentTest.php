<?php

namespace Tests\Feature\Models;

use App\Models\Attachment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * URL resolution lives on the Attachment model and is disk-aware: a public disk
 * returns a plain URL; an S3-compatible disk returns a SIGNED url cached on the
 * row, refreshed only when it nears expiry. These tests drive both branches by
 * faking the disk's `providesTemporaryUrls()`/`temporaryUrl()` contract.
 */
class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function attachment(array $overrides = []): Attachment
    {
        $order = Order::factory()->create();

        return $order->attachments()->create(array_merge([
            'disk' => 's3Custom',
            'path' => 'orders/attachments/file.pdf',
            'name' => 'file.pdf',
            'size' => 1234,
            'mime' => 'application/pdf',
        ], $overrides));
    }

    /**
     * Swap the named disk for a mock with the given temporary-URL behaviour.
     */
    private function fakeDisk(string $name, bool $signs, ?string $temporaryUrl = null): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('providesTemporaryUrls')->andReturn($signs);

        if ($signs) {
            $disk->shouldReceive('temporaryUrl')->andReturn($temporaryUrl ?? 'https://signed.example/file?sig=abc');
        } else {
            $disk->shouldReceive('url')->andReturn('https://public.example/file.pdf');
        }

        Storage::shouldReceive('disk')->with($name)->andReturn($disk);
    }

    public function test_public_disk_returns_plain_url_and_caches_nothing(): void
    {
        $this->fakeDisk('public', signs: false);

        $attachment = $this->attachment(['disk' => 'public']);

        $this->assertSame('https://public.example/file.pdf', $attachment->url());
        $this->assertNull($attachment->fresh()->cached_url);
        $this->assertNull($attachment->fresh()->cached_url_expires_at);
    }

    public function test_s3_disk_mints_signs_and_caches_the_url(): void
    {
        $this->fakeDisk('s3Custom', signs: true, temporaryUrl: 'https://signed.example/file?sig=fresh');

        $attachment = $this->attachment();

        $url = $attachment->url();

        $this->assertSame('https://signed.example/file?sig=fresh', $url);

        $fresh = $attachment->fresh();
        $this->assertSame($url, $fresh->cached_url);
        $this->assertNotNull($fresh->cached_url_expires_at);
        // TTL is ~60 min out.
        $this->assertTrue($fresh->cached_url_expires_at->isAfter(Carbon::now()->addMinutes(50)));
    }

    public function test_fresh_cached_url_is_reused_without_minting_again(): void
    {
        // A live cache (expires well beyond the refresh window) must be reused
        // verbatim — temporaryUrl() should never be called.
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('providesTemporaryUrls')->andReturn(true);
        $disk->shouldNotReceive('temporaryUrl');
        Storage::shouldReceive('disk')->with('s3Custom')->andReturn($disk);

        $attachment = $this->attachment([
            'cached_url' => 'https://signed.example/cached?sig=live',
            'cached_url_expires_at' => Carbon::now()->addMinutes(30),
        ]);

        $this->assertSame('https://signed.example/cached?sig=live', $attachment->url());
    }

    public function test_near_expiry_cached_url_is_refreshed(): void
    {
        // Within the refresh threshold (5 min) → mint a new one.
        $this->fakeDisk('s3Custom', signs: true, temporaryUrl: 'https://signed.example/file?sig=refreshed');

        $attachment = $this->attachment([
            'cached_url' => 'https://signed.example/old?sig=stale',
            'cached_url_expires_at' => Carbon::now()->addMinutes(2),
        ]);

        $this->assertSame('https://signed.example/file?sig=refreshed', $attachment->url());
        $this->assertSame('https://signed.example/file?sig=refreshed', $attachment->fresh()->cached_url);
    }

    public function test_attachment_belongs_to_owning_user(): void
    {
        $user = User::factory()->create();
        $attachment = $this->attachment(['user_id' => $user->id]);

        $this->assertTrue($attachment->user->is($user));
    }
}
