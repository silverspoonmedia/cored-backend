<?php

namespace Tests\Domain\Archivd\Unit\Support;

use App\Domain\Archivd\Support\PublicShareUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Archivd owns the guest share pages, so it owns their URLs.
 *
 * The kernel's `SpaPublicUrlTest` keeps the platform links (reset, verify,
 * payment); these two cases moved out of it when public sharing became
 * product-owned.
 */
class PublicShareUrlTest extends TestCase
{
    use RefreshDatabase;

    private const ID = '01900000-0000-7000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.spa.url' => '']);
    }

    #[Test]
    public function shares_fall_back_to_the_archivd_public_routes(): void
    {
        $this->assertSame(
            route('archivd.public.files.show', ['file' => self::ID]),
            PublicShareUrl::file(self::ID),
        );
        $this->assertSame(
            route('archivd.public.folders.show', ['folder' => self::ID]),
            PublicShareUrl::folder(self::ID),
        );
    }

    #[Test]
    public function shares_use_the_spa_origin_when_set(): void
    {
        config(['services.spa.url' => 'https://spa.test']);

        $this->assertSame('https://spa.test/s/f/'.self::ID, PublicShareUrl::file(self::ID));
        $this->assertSame('https://spa.test/s/d/'.self::ID, PublicShareUrl::folder(self::ID));
    }

    #[Test]
    public function rejects_path_traversal_in_the_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PublicShareUrl::file('../admin');
    }
}
